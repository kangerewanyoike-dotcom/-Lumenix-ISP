<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class RadiusSubscriberSync
{
    public function sync(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $username = $customer->pppoe_username;
            DB::table('radcheck')->where('username', $username)->delete();
            DB::table('radreply')->where('username', $username)->delete();
            DB::table('radusergroup')->where('username', $username)->delete();

            $customer->unsetRelation('plan');
            $customer->load('plan');

            if ($customer->status !== 'active' || $customer->trashed()) {
                DB::table('radcheck')->insert(['username' => $username, 'attribute' => 'Auth-Type', 'op' => ':=', 'value' => 'Reject']);
                return;
            }

            if (! $customer->plan || ! $customer->plan->active) {
                return;
            }

            DB::table('radcheck')->insert(['username' => $username, 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $customer->pppoe_password]);
            $rate = $customer->plan->speed_up_kbps.'k/'.$customer->plan->speed_down_kbps.'k';
            DB::table('radreply')->insert(['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit', 'op' => '=', 'value' => $rate]);
            DB::table('radusergroup')->insert(['username' => $username, 'groupname' => 'tenant-'.$customer->tenant_id.'-plan-'.$customer->plan_id, 'priority' => 1]);
        });
    }
}
