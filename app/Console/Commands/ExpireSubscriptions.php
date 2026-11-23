<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Jobs\DisconnectExpiredUsers;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire subscriber access at the end of a paid service period';

    public function handle(TenantContext $context): int
    {
        $expired = 0;
        foreach (Tenant::query()->where('status', 'active')->orderBy('id')->get() as $tenant) {
            $context->set($tenant);
            try {
                $today = CarbonImmutable::now($tenant->timezone ?? config('app.timezone'))->toDateString();
                Customer::query()->where('subscription_status', 'active')->where('status', 'active')
                    ->whereNotNull('current_period_ends_at')->whereDate('current_period_ends_at', '<', $today)->orderBy('id')->chunkById(100, function ($customers) use (&$expired, $tenant): void {
                        foreach ($customers as $customer) {
                            DB::transaction(function () use ($customer, $tenant, &$expired): void {
                                $locked = Customer::query()->lockForUpdate()->find($customer->id);
                                if (! $locked || $locked->subscription_status !== 'active' || $locked->current_period_ends_at?->isFuture()) {
                                    return;
                                }
                                $locked->update(['subscription_status' => 'expired', 'status' => 'suspended', 'suspension_reason' => 'subscription_expired', 'suspended_at' => now(), 'provisioning_status' => 'pending']);
                                app(AuditLogger::class)->record('subscription.expired', (int) $tenant->id, Customer::class, (string) $locked->id);
                                DisconnectExpiredUsers::dispatch((int) $tenant->id, (int) $locked->id)->afterCommit();
                                $expired++;
                            });
                        }
                    });
            } finally {
                $context->clear();
            }
        }
        $this->info("Expired {$expired} customer subscription(s).");

        return self::SUCCESS;
    }
}
