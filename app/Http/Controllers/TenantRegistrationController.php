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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class TenantRegistrationController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('tenant.select');
        }

        return view('auth.register', ['countries' => config('tenancy.countries')]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('tenant.select');
        }

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'tenant_name' => ['required', 'string', 'min:2', 'max:160'],
            'owner_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'country_code' => ['required', 'string', 'size:2', 'in:'.implode(',', array_keys(config('tenancy.countries')))],
            'brand_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $tenant = DB::transaction(function () use ($data): Tenant {
            $country = config('tenancy.countries.'.$data['country_code']);
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'slug' => Str::slug($data['tenant_name']).'-'.Str::lower(Str::random(6)),
                'status' => 'pending',
                'country_code' => $data['country_code'],
                'timezone' => $country['timezone'],
                'currency' => $country['currency'],
                'brand_name' => $data['tenant_name'],
                'brand_primary_color' => strtoupper($data['brand_primary_color']),
            ]);
            $user = User::create([
                'name' => $data['owner_name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
            ]);

            TenantMembership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
            ]);

            PlatformSubscription::create([
                'platform_plan_id' => PlatformPlan::query()->where('name', 'Free')->value('id'),
                'tenant_id' => $tenant->id,
                'price_per_subscriber_minor' => 0,
                'currency' => $country['currency'],
                'billing_day' => 1,
                'status' => 'active',
            ]);

            return $tenant;
        });

        Auth::login(User::query()->where('email', Str::lower($data['email']))->firstOrFail());
        $audit->record('tenant.application_submitted', null, Tenant::class, (string) $tenant->id);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', $tenant->id);

        return redirect()->route('tenant.select')
            ->with('status', 'Your '.$tenant->name.' application has been received. Workspace access begins after approval.');
    }
}
