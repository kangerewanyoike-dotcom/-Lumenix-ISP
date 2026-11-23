<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Jobs\SyncCustomerRadius;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class SyncRouterStatus extends Command
{
    protected $signature = 'network:sync-router-status';

    protected $description = 'Retry pending subscriber provisioning without changing router administrative status';

    public function handle(TenantContext $context): int
    {
        try {
            Cache::put('health:scheduler-last-seen', time(), now()->addMinutes(3));
        } catch (\Throwable $exception) {
            report($exception);
        }
        $queued = 0;
        foreach (Tenant::query()->where('status', 'active')->orderBy('id')->get() as $tenant) {
            $context->set($tenant);
            try {
                Customer::query()->where('status', 'active')->where('provisioning_status', 'pending')->orderBy('id')->chunkById(100, function ($customers) use (&$queued): void {
                    foreach ($customers as $customer) {
                        SyncCustomerRadius::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterCommit();
                        $queued++;
                    }
                });
            } finally {
                $context->clear();
            }
        }
        $this->info("Queued {$queued} pending subscriber provisioning sync(s).");

        return self::SUCCESS;
    }
}
