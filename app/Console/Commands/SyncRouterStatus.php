<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Jobs\SyncCustomerRadius;
use App\Models\Customer;
use App\Models\Router;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class SyncRouterStatus extends Command
{
    protected $signature = 'network:sync-router-status';

    protected $description = 'Mark stale routers offline and retry pending subscriber provisioning';

    public function handle(TenantContext $context): int
    {
        $cutoff = now()->subMinutes(5);
        $queued = 0;
        foreach (Tenant::query()->where('status', 'active')->orderBy('id')->get() as $tenant) {
            $context->set($tenant);
            try {
                Router::query()->where('status', 'active')->where(fn ($query) => $query->whereNull('telemetry_updated_at')->orWhere('telemetry_updated_at', '<', $cutoff))->update(['status' => 'inactive']);
                Router::query()->where('status', 'inactive')->where('telemetry_updated_at', '>=', $cutoff)->update(['status' => 'active']);
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
