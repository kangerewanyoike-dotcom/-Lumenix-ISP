<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantSelectionController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->platformAdmin()->exists()) {
            return redirect()->route('platform.dashboard');
        }

        $tenants = $request->user()->tenantMemberships()
            ->with('tenant')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($membership) => $membership->tenant->status === 'active');

        if ($tenants->count() === 1) {
            $request->session()->put('tenant_id', $tenants->first()->tenant_id);

            return redirect()->route('tenant.dashboard');
        }

        $pending = $request->user()->tenantMemberships()->with('tenant')->where('status', 'active')->get()
            ->filter(fn ($membership) => $membership->tenant->status === 'pending');
        if ($tenants->isEmpty() && $pending->isNotEmpty()) {
            return view('tenants.pending', ['tenants' => $pending->pluck('tenant')]);
        }

        return view('tenants.select', ['memberships' => $tenants]);
    }

    public function select(Request $request): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'integer']]);
        $membership = $request->user()->tenantMemberships()->with('tenant')
            ->where('tenant_id', $data['tenant_id'])->where('status', 'active')->first();
        abort_unless($membership, 403);
        abort_unless($membership->tenant->status === 'active', 403);

        $request->session()->put('tenant_id', $membership->tenant_id);

        return redirect()->route('tenant.dashboard');
    }
}
