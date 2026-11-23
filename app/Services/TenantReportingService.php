<?php

namespace App\Services;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RadiusAccountingSession;
use App\Models\HotspotVoucher;
use App\Models\Router;
use Carbon\CarbonImmutable;

final class TenantReportingService
{
    public function __construct(private readonly TenantContext $context) {}

    /** @return array<string, int> */
    public function summary(): array
    {
        $tenant = $this->context->current();
        $now = CarbonImmutable::now($tenant->timezone ?? config('app.timezone'));
        $start = $now->startOfMonth();
        $payments = Payment::query()->where('status', 'allocated')->whereBetween('paid_at', [$start, $now]);
        $sessions = RadiusAccountingSession::query()->where('started_at', '>=', $start);
        $activeSessions = RadiusAccountingSession::query()->whereNull('stopped_at');
        $routers = Router::query()->where('telemetry_updated_at', '>=', now()->subMinutes(5));
        $onlinePppoe = (clone $activeSessions)->where('access_type', 'pppoe')->distinct('username')->count('username');
        $onlineHotspot = (clone $activeSessions)->where('access_type', 'hotspot')->distinct('username')->count('username');
        $offlineCustomers = Customer::query()->whereIn('status', ['active', 'suspended'])->whereDoesntHave('radiusAccountingSessions', fn ($query) => $query->whereNull('stopped_at'))->count();
        $offlineVouchers = HotspotVoucher::query()->where('status', 'active')->whereDoesntHave('radiusSessions', fn ($query) => $query->whereNull('stopped_at'))->count();

        return [
            'active_customers' => Customer::query()->where('status', 'active')->count(),
            'online_pppoe_customers' => $onlinePppoe,
            'online_hotspot_customers' => $onlineHotspot,
            'offline_subscribers' => $offlineCustomers + $offlineVouchers,
            'active_routers' => (clone $routers)->where('status', 'active')->count(),
            'router_uptime_seconds' => (int) ((clone $routers)->max('uptime_seconds') ?? 0),
            'download_bps' => (int) ((clone $routers)->sum('download_bps')),
            'upload_bps' => (int) ((clone $routers)->sum('upload_bps')),
            'cpu_percent' => (float) ((clone $routers)->avg('cpu_percent') ?? 0),
            'memory_percent' => (float) ((clone $routers)->avg('memory_percent') ?? 0),
            'active_sessions' => (clone $activeSessions)->count(),
            'network_disconnections_24h' => RadiusAccountingSession::query()->where('stopped_at', '>=', now()->subDay())->count(),
            'active_connections' => RadiusAccountingSession::query()->whereNull('stopped_at')->count(),
            'suspended_customers' => Customer::query()->where('status', 'suspended')->count(),
            'month_revenue_minor' => (int) $payments->sum('amount_minor'),
            'outstanding_minor' => (int) Invoice::query()->where('balance_minor', '>', 0)->sum('balance_minor'),
            'outstanding_invoice_count' => Invoice::query()->where('balance_minor', '>', 0)->count(),
            'unmatched_payment_count' => Payment::query()->where('status', 'unmatched')->count(),
            'usage_input_bytes' => (int) $sessions->sum('input_octets'),
            'usage_output_bytes' => (int) $sessions->sum('output_octets'),
        ];
    }

    /** @return list<array{month:string,revenue_minor:int}> */
    public function revenueByMonth(int $months = 6): array
    {
        $tenant = $this->context->current();
        $now = CarbonImmutable::now($tenant->timezone ?? config('app.timezone'));
        $start = $now->subMonths($months - 1)->startOfMonth();
        $grouped = Payment::query()->where('status', 'allocated')->where('paid_at', '>=', $start)->get()
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->format('Y-m'));
        $series = [];
        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = $now->subMonths($offset)->startOfMonth();
            $series[] = ['month' => $month->format('M Y'), 'revenue_minor' => (int) $grouped->get($month->format('Y-m'), collect())->sum('amount_minor')];
        }

        return $series;
    }
}
