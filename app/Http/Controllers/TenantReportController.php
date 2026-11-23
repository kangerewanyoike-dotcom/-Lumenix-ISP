<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Invoice;
use App\Services\TenantReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantReportController extends Controller
{
    public function dashboard(TenantReportingService $reports, TenantContext $context): View
    {
        return view('tenant.dashboard', ['summary' => $reports->summary(), 'revenue' => $reports->revenueByMonth(6), 'currency' => $context->current()->currency]);
    }

    public function index(Request $request, TenantReportingService $reports): View
    {
        abort_unless(in_array($request->attributes->get('tenant_membership')?->role, ['owner', 'admin'], true), 403);

        return view('admin.reports.index', [
            'summary' => $reports->summary(), 'revenue' => $reports->revenueByMonth(12), 'currency' => app(TenantContext::class)->current()->currency,
            'outstandingInvoices' => Invoice::query()->with('customer')->where('balance_minor', '>', 0)->orderByDesc('balance_minor')->limit(20)->get(),
        ]);
    }
}
