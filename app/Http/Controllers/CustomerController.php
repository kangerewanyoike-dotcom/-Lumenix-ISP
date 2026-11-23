<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Requests\CustomerRequest;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\BillingService;
use App\Services\CustomerPrivacyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CustomerController extends Controller
{
    public function index(Request $request, AuditLogger $audit, TenantContext $context): View
    {
        $this->authorize('viewAny', Customer::class);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'connection' => ['nullable', Rule::in(['online', 'offline'])],
            'balance' => ['nullable', Rule::in(['outstanding', 'overdue'])],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->where(fn ($query) => $query->where('tenant_id', $context->id()))],
        ]);
        $today = CarbonImmutable::now($context->current()->timezone ?? config('app.timezone'))->toDateString();
        $query = Customer::query()
            ->with('plan')
            ->withSum('invoices as balance_due_minor', 'balance_minor')
            ->withMax('payments as last_payment_at', 'paid_at')
            ->withCount(['radiusAccountingSessions as active_session_count' => fn ($sessions) => $sessions->whereNull('stopped_at')])
            ->orderBy('name');
        if ($search = $data['q'] ?? null) {
            $query->where(function ($builder) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $builder->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(account_number) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(pppoe_username) LIKE ?', [$needle]);
            });
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['plan_id'])) {
            $query->where('plan_id', $data['plan_id']);
        }
        if (($data['connection'] ?? null) === 'online') {
            $query->whereHas('radiusAccountingSessions', fn ($sessions) => $sessions->whereNull('stopped_at'));
        } elseif (($data['connection'] ?? null) === 'offline') {
            $query->whereDoesntHave('radiusAccountingSessions', fn ($sessions) => $sessions->whereNull('stopped_at'));
        }
        if (($data['balance'] ?? null) === 'outstanding') {
            $query->whereHas('invoices', fn ($invoices) => $invoices->where('balance_minor', '>', 0));
        } elseif (($data['balance'] ?? null) === 'overdue') {
            $query->whereHas('invoices', fn ($invoices) => $invoices->where('balance_minor', '>', 0)->whereDate('due_at', '<', $today));
        }
        $audit->record('customer.listed', (int) $context->id(), Customer::class);

        return view('admin.customers.index', [
            'customers' => $query->paginate(20)->withQueryString(),
            'plans' => Plan::query()->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'total' => Customer::query()->count(),
                'active' => Customer::query()->where('status', 'active')->count(),
                'suspended' => Customer::query()->where('status', 'suspended')->count(),
                'online' => Customer::query()->whereHas('radiusAccountingSessions', fn ($sessions) => $sessions->whereNull('stopped_at'))->count(),
                'outstanding_minor' => (int) Invoice::query()->where('balance_minor', '>', 0)->sum('balance_minor'),
                'overdue' => Invoice::query()->where('balance_minor', '>', 0)->whereDate('due_at', '<', $today)->distinct('customer_id')->count('customer_id'),
            ],
            'currency' => $context->current()->currency,
            'money' => fn (int $minor): string => ($minor < 0 ? '-' : '').number_format(intdiv(abs($minor), 100)).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('admin.customers.create', ['plans' => Plan::query()->where('active', true)->orderBy('name')->get()]);
    }

    public function store(CustomerRequest $request, AuditLogger $audit, TenantContext $context, BillingService $billing): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $data = $request->validated();
        $now = CarbonImmutable::now($context->current()->timezone ?? config('app.timezone'))->startOfDay();
        $plan = Plan::query()->findOrFail($data['plan_id']);
        [$periodStart, $periodEnd] = match ($plan->billing_period) {
            'daily' => [$now, $now], 'weekly' => [$now, $now->addDays(6)], default => [$now, $now->addMonthNoOverflow()->subDay()],
        };
        $customer = Customer::create([
            ...$data, 'status' => 'suspended', 'subscription_status' => 'pending_payment',
            'suspension_reason' => 'payment_pending', 'suspended_at' => now(), 'provisioning_status' => 'blocked',
            'billing_anchor_date' => $now->toDateString(), 'current_period_ends_at' => $periodEnd->toDateString(),
        ]);
        $invoice = $billing->generateRecurringInvoice($customer, $periodStart, $periodEnd, 'activation:'.$customer->id.':'.$periodStart->toDateString());
        $audit->record('customer.created', (int) $context->id(), Customer::class, (string) $customer->id);

        return redirect()->route('admin.customers.show', $customer->id)->with('status', 'Customer created. Invoice '.$invoice->number.' is ready for payment. Internet access activates after verified payment or applied credit.');
    }

    public function show(string $customer, AuditLogger $audit, TenantContext $context): View
    {
        $customer = Customer::query()->with(['plan', 'portalAccount'])->findOrFail($customer);
        $this->authorize('view', $customer);
        $audit->record('customer.viewed', (int) $context->id(), Customer::class, (string) $customer->id);

        $today = CarbonImmutable::now($context->current()->timezone ?? config('app.timezone'))->startOfDay();
        $billingDay = $customer->billing_day;
        $planPeriod = $customer->plan?->billing_period ?? 'monthly';
        if ($planPeriod === 'weekly') {
            $anchor = $customer->billing_anchor_date ?? CarbonImmutable::parse($customer->created_at)->startOfDay();
            $elapsedDays = max(0, $anchor->diffInDays($today, false));
            $periodStart = $anchor->addDays(intdiv($elapsedDays, 7) * 7);
            $periodEnd = $periodStart->addDays(6);
        } elseif ($planPeriod === 'daily') {
            $periodStart = $today;
            $periodEnd = $today;
        } else {
            $periodStart = $today->day >= $billingDay
                ? $today->startOfMonth()->setDay($billingDay)
                : $today->subMonthNoOverflow()->startOfMonth()->setDay($billingDay);
            $periodEnd = $periodStart->addMonthNoOverflow()->subDay();
        }

        $recentInvoices = $customer->invoices()->latest('issued_at')->limit(10)->get();
        $recentPayments = $customer->payments()->latest('paid_at')->limit(10)->get();
        $recentSessions = $customer->radiusAccountingSessions()->latest('started_at')->limit(10)->get();
        $activeSessions = $customer->radiusAccountingSessions()->whereNull('stopped_at')->orderByDesc('started_at')->get();
        $usageFrom = $periodStart->startOfDay();
        $usageTo = min($periodEnd->endOfDay(), $today->endOfDay());
        $usage = $customer->radiusAccountingSessions()
            ->whereBetween('started_at', [$usageFrom, $usageTo])
            ->selectRaw('COALESCE(SUM(input_octets), 0) as input_octets, COALESCE(SUM(output_octets), 0) as output_octets')
            ->first();
        $outstandingMinor = (int) $customer->invoices()->sum('balance_minor');
        $overdueMinor = (int) $customer->invoices()->where('balance_minor', '>', 0)->whereDate('due_at', '<', $today->toDateString())->sum('balance_minor');

        return view('admin.customers.show', [
            'customer' => $customer,
            'plans' => Plan::query()->where('active', true)->where('billing_period', $planPeriod)->orderBy('name')->get(),
            'billingKey' => (string) Str::uuid(),
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'effectiveDate' => $today,
            'creditBalanceMinor' => (int) CreditLedgerEntry::query()->where('customer_id', $customer->id)->sum('amount_minor'),
            'recentInvoices' => $recentInvoices,
            'recentPayments' => $recentPayments,
            'recentSessions' => $recentSessions,
            'activeSessions' => $activeSessions,
            'usage' => $usage,
            'outstandingMinor' => $outstandingMinor,
            'overdueMinor' => $overdueMinor,
            'currency' => $context->current()->currency,
            'money' => fn (int $minor): string => ($minor < 0 ? '-' : '').number_format(intdiv(abs($minor), 100)).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT),
        ]);
    }

    public function savePortalAccount(Request $request, string $customer, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        $this->authorize('update', $customer);
        $existing = CustomerPortalAccount::query()->where('customer_id', $customer->id)->first();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254', Rule::unique('customer_portal_accounts', 'email')->where(fn ($query) => $query->where('tenant_id', $context->id()))->ignore($existing?->id)],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'min:12', 'confirmed', 'max:128'],
            'active' => ['nullable', 'boolean'],
        ]);
        $values = ['email' => mb_strtolower($data['email']), 'active' => (bool) ($data['active'] ?? false)];
        if (filled($data['password'] ?? null)) {
            $values['password'] = $data['password'];
        }
        $account = CustomerPortalAccount::query()->updateOrCreate(['customer_id' => $customer->id], $values);
        $audit->record('customer.portal_account_saved', (int) $context->id(), CustomerPortalAccount::class, (string) $account->id);

        return back()->with('status', 'Customer portal account saved. Share the login details with the customer through your usual secure channel.');
    }

    public function edit(string $customer, AuditLogger $audit, TenantContext $context): View
    {
        $customer = Customer::query()->with('plan')->findOrFail($customer);
        $this->authorize('update', $customer);
        $audit->record('customer.edit_viewed', (int) $context->id(), Customer::class, (string) $customer->id);

        return view('admin.customers.edit', [
            'customer' => $customer,
            'plans' => Plan::query()->orderBy('name')->get(),
        ]);
    }

    public function update(CustomerRequest $request, string $customer, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        $this->authorize('update', $customer);
        $data = $request->validated();
        if ($customer->status !== 'active' && ($data['status'] ?? null) === 'active') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Settle an invoice or apply approved credit to restore internet access.']);
        }
        if (blank($data['pppoe_password'] ?? null)) {
            unset($data['pppoe_password']);
        }
        if ($customer->status === 'suspended' && in_array($customer->suspension_reason, ['payment_pending', 'payment_overdue', 'subscription_expired'], true)) {
            $data['suspension_reason'] = $customer->suspension_reason;
            $data['suspended_at'] = $customer->suspended_at;
        } elseif ($data['status'] === 'suspended' && $customer->status !== 'suspended') {
            $data['suspension_reason'] = 'manual';
            $data['suspended_at'] = now();
        } elseif ($data['status'] === 'active') {
            $data['suspension_reason'] = null;
            $data['suspended_at'] = null;
        }
        $customer->update($data);
        $audit->record('customer.updated', (int) $context->id(), Customer::class, (string) $customer->id);

        return redirect()->route('admin.customers.show', $customer->id)->with('status', 'Customer updated.');
    }

    public function reactivate(string $customer, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        $this->authorize('update', $customer);
        abort_unless($customer->subscription_status === 'active', 422, 'Settle a renewal invoice before restoring internet access.');
        abort_if($customer->invoices()->where('balance_minor', '>', 0)->exists(), 422, 'Settle or credit outstanding invoices before restoring internet access.');
        if ($customer->status !== 'active') {
            $customer->update(['status' => 'active', 'suspension_reason' => null, 'suspended_at' => null, 'provisioning_status' => 'pending']);
        }
        $audit->record('customer.reactivated', (int) $context->id(), Customer::class, (string) $customer->id, ['source' => 'authorized_reactivation']);

        return back()->with('status', 'Subscription is active. RADIUS provisioning is queued; network connectivity will update from accounting.');
    }

    public function export(Request $request, string $customer, AuditLogger $audit, TenantContext $context): StreamedResponse
    {
        $customer = Customer::query()->with(['plan', 'invoices.lines'])->findOrFail($customer);
        $this->authorize('view', $customer);
        $this->authorizeManager($request);
        $payments = Payment::query()->where('customer_id', $customer->id)->orderBy('paid_at')->get();
        $usage = $customer->radiusAccountingSessions()->selectRaw('COUNT(*) as session_count, COALESCE(SUM(input_octets), 0) as input_octets, COALESCE(SUM(output_octets), 0) as output_octets')->first();
        $audit->record('customer.exported', (int) $context->id(), Customer::class, (string) $customer->id, ['format' => 'csv']);

        return response()->streamDownload(function () use ($customer, $payments, $usage): void {
            $output = fopen('php://output', 'w');
            self::writeCsvRow($output, ['Section', 'Field', 'Value']);
            foreach ([
                'name' => $customer->name, 'account_number' => $customer->account_number,
                'email' => $customer->email, 'phone' => $customer->phone, 'address' => $customer->address,
                'service_status' => $customer->status, 'pppoe_username' => $customer->pppoe_username,
                'plan' => $customer->plan?->name,
            ] as $field => $value) {
                self::writeCsvRow($output, ['customer', $field, $value]);
            }
            self::writeCsvRow($output, []);
            self::writeCsvRow($output, ['Invoices', 'Status', 'Currency', 'Total minor units', 'Balance minor units', 'Issued', 'Due']);
            foreach ($customer->invoices as $invoice) {
                self::writeCsvRow($output, [$invoice->number, $invoice->status, $invoice->currency, $invoice->total_minor, $invoice->balance_minor, $invoice->issued_at?->toDateString(), $invoice->due_at?->toDateString()]);
            }
            self::writeCsvRow($output, []);
            self::writeCsvRow($output, ['Payments', 'Receipt', 'Status', 'Currency', 'Amount minor units', 'Paid at']);
            foreach ($payments as $payment) {
                self::writeCsvRow($output, [$payment->receipt, $payment->status, $payment->currency, $payment->amount_minor, $payment->paid_at?->toIso8601String()]);
            }
            self::writeCsvRow($output, []);
            self::writeCsvRow($output, ['Usage', 'Sessions', 'Input bytes', 'Output bytes']);
            self::writeCsvRow($output, ['all recorded usage', (int) $usage->session_count, (int) $usage->input_octets, (int) $usage->output_octets]);
            fclose($output);
        }, 'customer-'.$customer->id.'-export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(string $customer, TenantContext $context, CustomerPrivacyService $privacy): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        $this->authorize('delete', $customer);
        $privacy->anonymizeAndArchive($customer);

        return redirect()->route('admin.customers.index')->with('status', 'Customer disconnected and archived.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless(in_array($request->attributes->get('tenant_membership')?->role, ['owner', 'admin'], true), 403);
    }

    /** @param resource $output */
    private static function writeCsvRow($output, array $values): void
    {
        $safe = array_map(static function ($value) {
            if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
                return "'".$value;
            }

            return $value;
        }, $values);
        fputcsv($output, $safe, ',', '"', '\\');
    }
}
