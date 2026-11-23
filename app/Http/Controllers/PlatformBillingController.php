<?php

namespace App\Http\Controllers;

use App\Models\PlatformInvoice;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PlatformBillingController extends Controller
{
    public function index(): View
    {
        return view('platform.billing.index', [
            'tenants' => Tenant::query()->with('platformSubscription.plan')->orderBy('name')->get(),
            'plans' => PlatformPlan::query()->where('active', true)->orderBy('monthly_price_minor')->get(),
            'invoices' => PlatformInvoice::query()->with('tenant')->orderByDesc('issued_at')->limit(100)->get(),
        ]);
    }

    public function update(Request $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'platform_plan_id' => ['nullable', 'integer', Rule::exists('platform_plans', 'id')->where(fn ($query) => $query->where('active', true))],
            'price_per_subscriber_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'status' => ['required', 'in:active,paused'],
        ]);
        if ($data['platform_plan_id'] ?? null) {
            $plan = PlatformPlan::query()->findOrFail($data['platform_plan_id']);
            $data['currency'] = $plan->currency;
        }
        $subscription = PlatformSubscription::query()->updateOrCreate(['tenant_id' => $tenant->id], $data);
        $audit->record('platform.subscription_updated', null, PlatformSubscription::class, (string) $subscription->id, ['tenant_id' => $tenant->id]);

        return back()->with('status', 'Platform subscription saved.');
    }

    public function generate(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $result = Artisan::call('platform-billing:run', ['--period' => $data['period']]);
        abort_if($result !== 0, 500, 'Platform invoice generation failed.');
        $audit->record('platform.invoices_generated', null, PlatformInvoice::class, null, ['status' => $data['period']]);

        return back()->with('status', trim(Artisan::output()) ?: 'Platform invoices generated.');
    }
}
