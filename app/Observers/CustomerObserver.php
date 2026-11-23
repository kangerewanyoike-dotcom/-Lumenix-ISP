<?php

namespace App\Observers;

use App\Models\Customer;
use App\Jobs\SyncCustomerRadius;
use Illuminate\Support\Facades\DB;

final class CustomerObserver
{
    /** @var array<int, string> */
    private static array $previousUsernames = [];

    public function updating(Customer $customer): void
    {
        if ($customer->isDirty('pppoe_username')) {
            self::$previousUsernames[(int) $customer->id] = (string) $customer->getOriginal('pppoe_username');
        }
    }

    public function updated(Customer $customer): void
    {
        $previousUsername = self::$previousUsernames[(int) $customer->id] ?? null;
        unset(self::$previousUsernames[(int) $customer->id]);
        if (! $previousUsername) {
            return;
        }

        foreach (['radcheck', 'radreply', 'radusergroup'] as $table) {
            DB::table($table)->where('username', $previousUsername)->delete();
        }
    }

    public function saved(Customer $customer): void
    {
        SyncCustomerRadius::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterCommit();
    }

    public function deleted(Customer $customer): void
    {
        SyncCustomerRadius::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterCommit();
    }
}
