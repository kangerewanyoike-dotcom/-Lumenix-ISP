<?php

namespace App\Jobs;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\RadiusSubscriberSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SyncCustomerRadius implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    // Keep the uniqueness lock for the full retry window to avoid the minute scheduler
    // dispatching duplicate work while this job is backing off.
    public int $uniqueFor = 900;

    public function __construct(public readonly int $tenantId, public readonly int $customerId) {}

    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->customerId;
    }

    public function handle(TenantContext $context, RadiusSubscriberSync $sync): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $context->set($tenant);
        try {
            $customer = Customer::query()->withTrashed()->findOrFail($this->customerId);
            $sync->sync($customer);
            $customer->load('plan');
            $state = $customer->status !== 'active' || $customer->trashed()
                ? 'blocked'
                : (! $customer->plan || ! $customer->plan->active
                    ? 'failed'
                    : 'ready');
            DB::table('customers')->where('tenant_id', $tenant->id)->where('id', $customer->id)->update([
                'provisioning_status' => $state,
                'provisioned_at' => in_array($state, ['ready', 'blocked'], true) ? now() : null,
                'updated_at' => now(),
            ]);
        } finally {
            $context->clear();
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('customers')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $this->customerId)
            ->where('provisioning_status', 'pending')
            ->update([
                'provisioning_status' => 'failed',
                'provisioned_at' => null,
                'updated_at' => now(),
            ]);

        if ($exception) {
            report($exception);
        }
    }
}
