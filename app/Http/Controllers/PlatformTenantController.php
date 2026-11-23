<?php

namespace App\Http\Controllers;

use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PlatformTenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()
            ->with(['platformSubscription.plan', 'memberships.user'])
            ->withCount('memberships')
            ->addSelect(['customers_count' => DB::table('customers')
                ->selectRaw('COUNT(*)')
                ->whereColumn('customers.tenant_id', 'tenants.id')
                ->whereNull('customers.deleted_at')])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('platform.tenants.index', ['tenants' => $tenants, 'countries' => config('tenancy.countries')]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->merge(['owner_email' => Str::lower(trim((string) $request->input('owner_email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', 'unique:tenants,slug'],
            'owner_name' => ['required', 'string', 'max:160'],
            'owner_email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'country_code' => ['required', 'string', 'size:2', 'in:'.implode(',', array_keys(config('tenancy.countries')))],
        ]);
        $country = config('tenancy.countries.'.$data['country_code']);
        $tenant = DB::transaction(function () use ($data, $country): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
                'country_code' => $data['country_code'],
                'currency' => $country['currency'],
                'timezone' => $country['timezone'],
                'status' => 'active',
                'approved_at' => now(),
            ]);
            $owner = User::create(['name' => $data['owner_name'], 'email' => Str::lower($data['owner_email']), 'password' => Str::random(48)]);
            TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
            $freePlanId = PlatformPlan::query()->where('name', 'Free')->value('id');
            PlatformSubscription::create(['tenant_id' => $tenant->id, 'platform_plan_id' => $freePlanId, 'price_per_subscriber_minor' => 0, 'currency' => $tenant->currency, 'billing_day' => 1, 'status' => 'active']);

            return $tenant;
        });
        $audit->record('tenant.created', null, Tenant::class, (string) $tenant->id);

        return back()->with('status', 'ISP registered with an owner account. Ask the owner to use Forgot password to set their password.');
    }

    public function suspend(Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $tenant->update(['status' => 'suspended', 'suspended_at' => now()]);
        $audit->record('tenant.suspended', null, Tenant::class, (string) $tenant->id);

        return back()->with('status', 'Tenant suspended.');
    }

    public function activate(Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $tenant->update(['status' => 'active', 'suspended_at' => null, 'approved_at' => $tenant->approved_at ?? now()]);
        $audit->record('tenant.activated', null, Tenant::class, (string) $tenant->id);

        return back()->with('status', 'Tenant activated.');
    }

    public function approve(Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        abort_unless($tenant->status === 'pending', 409, 'Only pending ISP applications can be approved.');
        $tenant->update(['status' => 'active', 'approved_at' => now()]);
        $audit->record('tenant.approved', null, Tenant::class, (string) $tenant->id);

        return back()->with('status', 'ISP approved and its workspace activated.');
    }

    public function cancel(Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($tenant->status, ['active', 'suspended'], true), 409, 'Only an active or suspended ISP can be cancelled.');
        $tenant->update(['status' => 'cancelled', 'suspended_at' => now()]);
        $audit->record('tenant.cancelled', null, Tenant::class, (string) $tenant->id);

        return back()->with('status', 'ISP subscription access cancelled. The workspace data is retained.');
    }
}
