<?php

namespace App\Observers;

use App\Models\Plan;
use App\Jobs\SyncCustomerRadius;

final class PlanObserver
{
    public function saved(Plan $plan): void
    {
        $plan->customers()->each(fn ($customer) => SyncCustomerRadius::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterCommit());
    }
}
