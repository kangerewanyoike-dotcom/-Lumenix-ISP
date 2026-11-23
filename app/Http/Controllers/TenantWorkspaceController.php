<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RadiusAccountingSession;
use App\Models\Router;
use App\Services\TenantReportingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class TenantWorkspaceController extends Controller
{
    public function pppoe(): View
    {
        $accounts = Customer::query()->with('plan')
            ->withCount(['radiusAccountingSessions as active_sessions_count' => fn ($query) => $query->whereNull('stopped_at')])
            ->orderBy('name')->paginate(25);

        return view('admin.workspace.pppoe', ['accounts' => $accounts]);
    }

    public function subscriptions(): View
    {
        $customers = Customer::query()->with('plan')
            ->withCount(['radiusAccountingSessions as active_sessions_count' => fn ($query) => $query->whereNull('stopped_at')])
            ->orderBy('name')->paginate(25);

        return view('admin.workspace.subscriptions', ['customers' => $customers]);
    }

    public function network(TenantReportingService $reports): View
    {
        $routers = Router::query()->orderBy('name')->get();
        try {
            $queueWorkerSeenAt = Cache::get('health:queue-worker-last-seen');
            $schedulerSeenAt = Cache::get('health:scheduler-last-seen');
        } catch (\Throwable) {
            $queueWorkerSeenAt = null;
            $schedulerSeenAt = null;
        }

        return view('admin.workspace.network', [
            'routers' => $routers,
            'sessions' => RadiusAccountingSession::query()->with('customer')->whereNull('stopped_at')->orderByDesc('started_at')->limit(30)->get(),
            'summary' => $reports->summary(),
            'activeCount' => RadiusAccountingSession::query()->whereNull('stopped_at')->count(),
            'downloadBytes' => (int) RadiusAccountingSession::query()->whereNull('stopped_at')->sum('input_octets'),
            'uploadBytes' => (int) RadiusAccountingSession::query()->whereNull('stopped_at')->sum('output_octets'),
            'failedProvisioningCount' => Customer::query()->where('provisioning_status', 'failed')->count(),
            'staleProvisioningCount' => Customer::query()->where('provisioning_status', 'pending')->where('updated_at', '<', now()->subMinutes(10))->count(),
            'routersWithoutFreshTelemetry' => $routers->filter(fn (Router $router): bool => ! $router->telemetry_updated_at || $router->telemetry_updated_at->lt(now()->subMinutes(5)))->count(),
            'queueWorkerHealthy' => is_numeric($queueWorkerSeenAt) && (int) $queueWorkerSeenAt >= now()->subMinutes(3)->timestamp,
            'queueWorkerSeenAt' => $queueWorkerSeenAt,
            'schedulerHealthy' => is_numeric($schedulerSeenAt) && (int) $schedulerSeenAt >= now()->subMinutes(3)->timestamp,
            'schedulerSeenAt' => $schedulerSeenAt,
        ]);
    }

    public function payments(): View
    {
        return view('admin.workspace.payments', [
            'payments' => Payment::query()->with('customer')->latest('paid_at')->paginate(25),
            'unmatchedCount' => Payment::query()->where('status', 'unmatched')->count(),
            'accounts' => \App\Models\DarajaAccount::query()->orderBy('name')->get(),
        ]);
    }

    public function hotspot(): View
    {
        return view('admin.workspace.hotspot', [
            'routers' => Router::query()->orderBy('name')->get(),
            'plans' => \App\Models\Plan::query()->where('active', true)->orderBy('name')->get(),
            'activeSessions' => RadiusAccountingSession::query()->whereNull('stopped_at')->count(),
        ]);
    }
}
