<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PlatformApiController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => [
            'tenants_total' => Tenant::query()->count(),
            'tenants_active' => Tenant::query()->where('status', 'active')->count(),
            'tenants_pending' => Tenant::query()->where('status', 'pending')->count(),
            'active_subscriptions' => PlatformSubscription::query()->where('status', 'active')->count(),
            'outstanding_minor' => (int) PlatformInvoice::query()->where('balance_minor', '>', 0)->sum('balance_minor'),
            'revenue_month_to_date' => PlatformPayment::query()->where('status', 'confirmed')->where('paid_at', '>=', now()->startOfMonth())->select('currency', DB::raw('SUM(amount_minor) as amount_minor'))->groupBy('currency')->get(),
        ]]);
    }

    public function tenants(): JsonResponse
    {
        $tenants = Tenant::query()
            ->with('platformSubscription.plan')
            ->withCount('memberships')
            ->addSelect(['customers_count' => DB::table('customers')
                ->selectRaw('COUNT(*)')
                ->whereColumn('customers.tenant_id', 'tenants.id')
                ->whereNull('customers.deleted_at')])
            ->orderBy('name')
            ->paginate(30);

        return response()->json(['data' => $tenants]);
    }

    public function tenant(Tenant $tenant): JsonResponse
    {
        $tenant->load('platformSubscription.plan')->loadCount('memberships');
        $tenant->setAttribute('customers_count', DB::table('customers')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count());

        return response()->json(['data' => $tenant]);
    }

    public function createTenant(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', 'unique:tenants,slug'],
            'owner_name' => ['required', 'string', 'max:160'], 'owner_email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'country_code' => ['sometimes', 'string', 'size:2', 'in:'.implode(',', array_keys(config('tenancy.countries')))],
        ]);
        $countryCode = $data['country_code'] ?? 'KE';
        $country = config('tenancy.countries.'.$countryCode);
        $tenant = DB::transaction(function () use ($data, $countryCode, $country): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $data['name'], 'slug' => $data['slug'] ?: Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
                'country_code' => $countryCode, 'currency' => $country['currency'], 'timezone' => $country['timezone'],
                'status' => 'active', 'approved_at' => now(),
            ]);
            $owner = User::query()->create(['name' => $data['owner_name'], 'email' => Str::lower($data['owner_email']), 'password' => Str::random(48)]);
            TenantMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
            $planId = PlatformPlan::query()->where('name', 'Free')->value('id');
            if ($planId) {
                PlatformSubscription::query()->create(['tenant_id' => $tenant->id, 'platform_plan_id' => $planId, 'price_per_subscriber_minor' => 0, 'currency' => $tenant->currency, 'billing_day' => 1, 'status' => 'active']);
            }

            return $tenant;
        });
        $audit->record('tenant.created', null, Tenant::class, (string) $tenant->id, ['source' => 'api']);

        return response()->json(['data' => $tenant, 'owner_password_setup' => 'The owner must use the password reset flow to set a password.'], 201);
    }

    public function tenantStatus(Request $request, Tenant $tenant, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $tenant->update(['status' => $data['status'], 'suspended_at' => $data['status'] === 'suspended' ? now() : null]);
        $audit->record('tenant.'.$data['status'], null, Tenant::class, (string) $tenant->id, ['source' => 'api']);

        return response()->json(['data' => $tenant->refresh()]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => PlatformPlan::query()->orderBy('monthly_price_minor')->get()]);
    }

    public function createPlan(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:platform_plans,name'],
            'monthly_price_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'included_subscribers' => ['required', 'integer', 'min:0'], 'max_routers' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'], 'active' => ['sometimes', 'boolean'],
        ]);
        $plan = PlatformPlan::query()->create([...$data, 'active' => $data['active'] ?? true]);
        $audit->record('platform.plan_created', null, PlatformPlan::class, (string) $plan->id, ['source' => 'api']);

        return response()->json(['data' => $plan], 201);
    }

    public function subscriptions(): JsonResponse
    {
        return response()->json(['data' => PlatformSubscription::query()->with(['tenant', 'plan'])->latest()->paginate(30)]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $payments = PlatformPayment::query()->where('status', 'confirmed')
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('paid_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('paid_at', '<=', $to));

        return response()->json(['data' => [
            'totals_by_currency' => (clone $payments)->select('currency', DB::raw('SUM(amount_minor) as amount_minor'))->groupBy('currency')->get(),
            'outstanding_by_currency' => PlatformInvoice::query()->where('balance_minor', '>', 0)->select('currency', DB::raw('SUM(balance_minor) as balance_minor'))->groupBy('currency')->get(),
            'churn_events' => AuditLog::query()->where('action', 'tenant.cancelled')->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))->count(),
        ]]);
    }
}
