<?php

namespace Database\Seeders;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $north = Tenant::firstOrCreate(['slug' => 'demo-north'], ['name' => 'Demo North ISP', 'status' => 'active']);
        $south = Tenant::firstOrCreate(['slug' => 'demo-south'], ['name' => 'Demo South ISP', 'status' => 'active']);

        foreach ([$north, $south] as $tenant) {
            $context = app(TenantContext::class);
            $context->set($tenant);
            try {
                $plan = Plan::firstOrCreate(['name' => 'Home 20 Mbps'], [
                    'price_minor' => 250000, 'currency' => 'KES', 'speed_down_kbps' => 20000,
                    'speed_up_kbps' => 5000, 'billing_period' => 'monthly', 'active' => true,
                ]);
                Customer::firstOrCreate(['account_number' => 'DEMO-001'], [
                    'plan_id' => $plan->id, 'name' => 'Demo Subscriber', 'phone' => '+254700000001',
                    'pppoe_username' => 'demo-'.str_replace('-', '', $tenant->slug),
                    'pppoe_password' => bin2hex(random_bytes(16)), 'status' => 'active', 'billing_day' => 1,
                ]);
                Router::firstOrCreate(['name' => 'Demo Router'], [
                    'connection_mode' => 'agent', 'agent_id' => 'demo-'.str_replace('-', '', $tenant->slug),
                    'api_port' => 8729, 'use_tls' => true, 'status' => 'inactive',
                ]);
            } finally {
                $context->clear();
            }
        }

        $adminEmail = env('LUMENIX_SEED_ADMIN_EMAIL');
        $adminPassword = env('LUMENIX_SEED_ADMIN_PASSWORD');
        if ($adminEmail && $adminPassword) {
            $admin = User::firstOrCreate(['email' => $adminEmail], [
                'name' => 'Lumenix Platform Admin',
                'password' => $adminPassword,
                'email_verified_at' => now(),
            ]);
            PlatformAdmin::firstOrCreate(['user_id' => $admin->id]);
        }

        $ownerEmail = env('LUMENIX_SEED_OWNER_EMAIL');
        $ownerPassword = env('LUMENIX_SEED_OWNER_PASSWORD');
        if ($ownerEmail && $ownerPassword) {
            $owner = User::firstOrCreate(['email' => $ownerEmail], [
                'name' => 'Demo ISP Owner',
                'password' => $ownerPassword,
                'email_verified_at' => now(),
            ]);
            TenantMembership::firstOrCreate(
                ['tenant_id' => $north->id, 'user_id' => $owner->id],
                ['role' => 'owner', 'status' => 'active'],
            );
        }
    }
}
