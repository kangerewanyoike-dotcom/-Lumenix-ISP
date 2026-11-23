<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\PlatformSupportTicket;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PlatformOperationsController extends Controller
{
    public function dashboard(): View
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $tenants = Tenant::query()->with('platformSubscription.plan')->get();
        $mrrByCurrency = [];
        foreach ($tenants->where('status', 'active') as $tenant) {
            $subscription = $tenant->platformSubscription;
            if (! $subscription || $subscription->status !== 'active') {
                continue;
            }
            $activeSubscribers = (int) DB::table('customers')->where('tenant_id', $tenant->id)->where('status', 'active')->count();
            $plan = $subscription->plan;
            $overage = $plan ? max(0, $activeSubscribers - $plan->included_subscribers) : $activeSubscribers;
            $currency = $plan?->currency ?? $subscription->currency;
            $mrrByCurrency[$currency] = ($mrrByCurrency[$currency] ?? 0) + ($plan?->monthly_price_minor ?? 0) + ($overage * $subscription->price_per_subscriber_minor);
        }

        return view('platform.dashboard', [
            'totalIsps' => $tenants->count(),
            'activeIsps' => $tenants->where('status', 'active')->count(),
            'pendingIsps' => $tenants->where('status', 'pending')->count(),
            'activeSubscriptions' => PlatformSubscription::query()->where('status', 'active')->whereHas('tenant', fn ($query) => $query->where('status', 'active'))->count(),
            'churnedThisMonth' => AuditLog::query()->where('action', 'tenant.cancelled')->where('created_at', '>=', $monthStart)->count(),
            'mrrByCurrency' => $mrrByCurrency,
            'arrByCurrency' => array_map(fn (int $amount): int => $amount * 12, $mrrByCurrency),
            'monthRevenue' => PlatformPayment::query()->where('status', 'confirmed')->where('paid_at', '>=', $monthStart)->select('currency', DB::raw('SUM(amount_minor) as total_minor'))->groupBy('currency')->get(),
            'outstandingByCurrency' => PlatformInvoice::query()->where('balance_minor', '>', 0)->select('currency', DB::raw('SUM(balance_minor) as total_minor'))->groupBy('currency')->get(),
            'openTickets' => PlatformSupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'recentPayments' => PlatformPayment::query()->with('tenant')->latest('paid_at')->limit(8)->get(),
            'recentTenants' => $tenants->sortByDesc('created_at')->take(6),
        ]);
    }

    public function plans(): View
    {
        return view('platform.plans.index', ['plans' => PlatformPlan::query()->orderBy('monthly_price_minor')->get()]);
    }

    public function updatePlan(Request $request, PlatformPlan $plan, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'monthly_price_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'included_subscribers' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_routers' => ['required', 'integer', 'min:1', 'max:100000'],
            'active' => ['required', 'boolean'],
        ]);
        $plan->update($data);
        PlatformSubscription::query()->where('platform_plan_id', $plan->id)->update(['currency' => $plan->currency]);
        $audit->record('platform.plan_updated', null, PlatformPlan::class, (string) $plan->id);

        return back()->with('status', 'SaaS plan updated.');
    }

    public function payments(): View
    {
        return view('platform.payments.index', [
            'payments' => PlatformPayment::query()->with(['tenant', 'invoice'])->latest('created_at')->paginate(30),
            'invoices' => PlatformInvoice::query()->with('tenant')->where('balance_minor', '>', 0)->orderBy('due_at')->limit(300)->get(),
            'currencyTotals' => PlatformPayment::query()->where('status', 'confirmed')->select('currency', DB::raw('SUM(amount_minor) as total_minor'))->groupBy('currency')->get(),
        ]);
    }

    public function recordPayment(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'platform_invoice_id' => ['required', 'integer', Rule::exists('platform_invoices', 'id')],
            'provider' => ['required', Rule::in(['mpesa', 'bank', 'card', 'manual'])],
            'reference' => ['required', 'string', 'max:120', 'unique:platform_payments,reference'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'paid_at' => ['required', 'date'],
        ]);
        DB::transaction(function () use ($data, $audit): void {
            $invoice = PlatformInvoice::query()->lockForUpdate()->findOrFail($data['platform_invoice_id']);
            abort_unless($data['amount_minor'] <= $invoice->balance_minor, 422, 'Payment cannot exceed the invoice balance.');
            $payment = PlatformPayment::query()->create([
                'tenant_id' => $invoice->tenant_id, 'platform_invoice_id' => $invoice->id,
                'provider' => $data['provider'], 'reference' => $data['reference'],
                'amount_minor' => $data['amount_minor'], 'currency' => $invoice->currency,
                'status' => 'confirmed', 'paid_at' => $data['paid_at'], 'recorded_by' => auth()->id(),
            ]);
            $invoice->balance_minor -= (int) $payment->amount_minor;
            $invoice->status = $invoice->balance_minor === 0 ? 'paid' : 'partial';
            $invoice->save();
            $audit->record('platform.payment_recorded', null, PlatformPayment::class, (string) $payment->id, ['tenant_id' => $invoice->tenant_id, 'amount_minor' => $payment->amount_minor, 'channel' => $payment->provider]);
        });

        return back()->with('status', 'Payment recorded and the platform invoice balance updated.');
    }

    public function tickets(): View
    {
        return view('platform.support.index', [
            'tickets' => PlatformSupportTicket::query()->with(['tenant', 'assignee'])->latest()->paginate(30),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function createTicket(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', Rule::exists('tenants', 'id')],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);
        $ticket = PlatformSupportTicket::query()->create([...$data, 'status' => 'open', 'created_by' => auth()->id()]);
        $audit->record('platform.ticket_created', null, PlatformSupportTicket::class, (string) $ticket->id, ['tenant_id' => $ticket->tenant_id]);

        return back()->with('status', 'Support ticket added to the platform queue.');
    }

    public function updateTicket(Request $request, PlatformSupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'resolved', 'closed'])]]);
        $ticket->update([...$data, 'assigned_to' => auth()->id()]);
        $audit->record('platform.ticket_updated', null, PlatformSupportTicket::class, (string) $ticket->id, ['tenant_id' => $ticket->tenant_id, 'status' => $ticket->status]);

        return back()->with('status', 'Support ticket status updated.');
    }

    public function monitoring(): View
    {
        $database = false;
        $redis = false;
        try {
            $queueWorkerSeenAt = Cache::get('health:queue-worker-last-seen');
            $schedulerSeenAt = Cache::get('health:scheduler-last-seen');
        } catch (\Throwable) {
            $queueWorkerSeenAt = null;
            $schedulerSeenAt = null;
        }
        $failedQueueJobs = 0;
        try {
            DB::select('select 1');
            $database = true;
        } catch (\Throwable) {
        }
        try {
            Redis::connection()->ping();
            $redis = true;
        } catch (\Throwable) {
        }
        try {
            $failedQueueJobs = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
        }

        return view('platform.monitoring', [
            'checks' => [
                'Application' => true,
                'Database' => $database,
                'Queue / Redis' => $redis,
                'Queue worker' => is_numeric($queueWorkerSeenAt) && (int) $queueWorkerSeenAt >= now()->subMinutes(3)->timestamp,
                'Scheduler' => is_numeric($schedulerSeenAt) && (int) $schedulerSeenAt >= now()->subMinutes(3)->timestamp,
            ],
            'failedQueueJobs' => $failedQueueJobs,
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'uptime' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            'diskFree' => @disk_free_space(base_path()),
            'diskTotal' => @disk_total_space(base_path()),
            'checkedAt' => now(),
        ]);
    }

    public function audit(): View
    {
        return view('platform.audit.index', ['logs' => AuditLog::query()->with('actor')->latest('created_at')->paginate(50)]);
    }

    public function settings(): View
    {
        $settings = DB::table('platform_settings')->pluck('value', 'key');

        return view('platform.settings', ['settings' => $settings]);
    }

    public function updateSettings(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:100'],
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'support_email' => ['nullable', 'email', 'max:254'],
            'mpesa_environment' => ['required', Rule::in(['sandbox', 'production'])],
        ]);
        foreach ($data as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        $audit->record('platform.settings_updated');

        return back()->with('status', 'Platform settings saved.');
    }
}
