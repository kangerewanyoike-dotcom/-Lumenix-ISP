<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\PlanRequest;
use App\Http\Requests\RouterRequest;
use App\Models\Customer;
use App\Models\DarajaAccount;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\RadiusAccountingSession;
use App\Models\Router;
use App\Services\AuditLogger;
use App\Services\DarajaClient;
use App\Services\BillingService;
use App\Services\TenantReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TenantApiController extends Controller
{
    public function dashboard(TenantReportingService $reports, TenantContext $context): JsonResponse
    {
        return response()->json(['data' => [
            'tenant' => ['id' => $context->id(), 'name' => $context->current()->name],
            'billing' => $reports->summary(), 'revenue' => $reports->revenueByMonth(6),
            'network' => array_intersect_key($reports->summary(), array_flip(['online_pppoe_customers', 'online_hotspot_customers', 'offline_subscribers', 'active_routers', 'router_uptime_seconds', 'download_bps', 'upload_bps', 'cpu_percent', 'memory_percent', 'active_sessions', 'network_disconnections_24h'])),
        ]]);
    }

    public function customers(): JsonResponse
    {
        return response()->json(['data' => Customer::query()->with('plan')->withCount(['radiusAccountingSessions as active_sessions_count' => fn ($query) => $query->whereNull('stopped_at')])->orderBy('name')->paginate(30)]);
    }

    public function createCustomer(CustomerRequest $request, AuditLogger $audit, BillingService $billing, TenantContext $context): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $data = $request->validated();
        $now = CarbonImmutable::now($context->current()->timezone ?? config('app.timezone'))->startOfDay();
        $plan = Plan::query()->findOrFail($data['plan_id']);
        [$periodStart, $periodEnd] = match ($plan->billing_period) {
            'daily' => [$now, $now], 'weekly' => [$now, $now->addDays(6)], default => [$now, $now->addMonthNoOverflow()->subDay()],
        };
        $customer = Customer::query()->create([
            ...$data, 'status' => 'suspended', 'subscription_status' => 'pending_payment', 'suspension_reason' => 'payment_pending',
            'suspended_at' => now(), 'provisioning_status' => 'blocked', 'billing_anchor_date' => $now->toDateString(),
            'current_period_ends_at' => $periodEnd->toDateString(),
        ]);
        $invoice = $billing->generateRecurringInvoice($customer, $periodStart, $periodEnd, 'activation:'.$customer->id.':'.$periodStart->toDateString());
        $audit->record('customer.created', (int) $customer->tenant_id, Customer::class, (string) $customer->id, ['source' => 'api']);

        return response()->json(['data' => ['customer' => $customer->load('plan'), 'initial_invoice' => $invoice]], 201);
    }

    public function packages(): JsonResponse
    {
        return response()->json(['data' => Plan::query()->orderBy('name')->paginate(30)]);
    }

    public function createPackage(PlanRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', Plan::class);
        $plan = Plan::query()->create([...$request->validated(), 'currency' => app(TenantContext::class)->current()->currency]);
        $audit->record('plan.created', (int) $plan->tenant_id, Plan::class, (string) $plan->id, ['source' => 'api']);

        return response()->json(['data' => $plan], 201);
    }

    public function routers(): JsonResponse
    {
        $this->authorize('viewAny', Router::class);

        return response()->json(['data' => Router::query()->orderBy('name')->paginate(30)]);
    }

    public function createRouter(RouterRequest $request, AuditLogger $audit, TenantContext $context): JsonResponse
    {
        $this->authorize('create', Router::class);
        $subscription = \App\Models\PlatformSubscription::query()->with('plan')->where('tenant_id', $context->id())->where('status', 'active')->first();
        if ($subscription?->plan && Router::query()->count() >= $subscription->plan->max_routers) {
            abort(422, 'The current SaaS plan router limit has been reached.');
        }
        $data = $request->validated();
        if ($data['connection_mode'] === 'agent') {
            $data['endpoint'] = $data['api_username'] = $data['api_password'] = $data['radius_secret'] = null;
        }
        $router = Router::query()->create($data);
        $audit->record('router.created', (int) $context->id(), Router::class, (string) $router->id, ['connection_mode' => $router->connection_mode, 'source' => 'api']);

        return response()->json(['data' => $router], 201);
    }

    public function createInvoice(Request $request, BillingService $billing, TenantContext $context): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $context->id())],
            'period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
        ]);
        $this->authorize('create', Invoice::class);
        $key = $request->header('Idempotency-Key') ?: $request->validate(['idempotency_key' => ['required', 'string', 'max:191']])['idempotency_key'];
        $invoice = $billing->generateRecurringInvoice(
            Customer::query()->findOrFail($data['customer_id']),
            CarbonImmutable::parse($data['period_start']), CarbonImmutable::parse($data['period_end']), $key,
        );

        return response()->json(['data' => $invoice], 201);
    }

    public function stkPush(Request $request, DarajaClient $daraja, TenantContext $context): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')->where('tenant_id', $context->id())],
            'daraja_account_id' => ['required', 'integer', Rule::exists('daraja_accounts', 'id')->where('tenant_id', $context->id())->where('active', true)],
            'phone' => ['required', 'string', 'regex:/^\+?[1-9][0-9]{6,14}$/'],
        ]);
        $key = $request->header('Idempotency-Key') ?: $request->validate(['idempotency_key' => ['required', 'string', 'max:191']])['idempotency_key'];
        $requestRecord = $daraja->initiate(
            DarajaAccount::query()->findOrFail($data['daraja_account_id']),
            Invoice::query()->findOrFail($data['invoice_id']), $data['phone'], $key,
        );

        return response()->json(['data' => [
            'payment_request_id' => $requestRecord->id, 'status' => $requestRecord->status,
            'checkout_request_id' => $requestRecord->checkout_request_id,
            'message' => 'Payment is pending provider confirmation; internet access is reported separately from payment status.',
        ]], 202);
    }

    public function revenue(TenantReportingService $reports, TenantContext $context): JsonResponse
    {
        return response()->json(['data' => ['currency' => $context->current()->currency, 'summary' => $reports->summary(), 'monthly' => $reports->revenueByMonth(12)]]);
    }

}
