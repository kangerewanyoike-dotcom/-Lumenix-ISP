<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BrandingController extends Controller
{
    public function index(TenantContext $context): View
    {
        abort_unless(in_array(request()->attributes->get('tenant_membership')?->role, ['owner', 'admin'], true), 403);

        return view('admin.branding.index', ['tenant' => $context->current()]);
    }

    public function update(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($request->attributes->get('tenant_membership')?->role, ['owner', 'admin'], true), 403);
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:160'],
            'brand_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'powered_by_enabled' => ['nullable', 'boolean'],
        ]);
        $tenant = $context->current();
        $tenant->update([
            'brand_name' => $data['brand_name'] ?: null,
            'brand_primary_color' => strtoupper($data['brand_primary_color']),
            'powered_by_enabled' => (bool) ($data['powered_by_enabled'] ?? false),
        ]);
        $audit->record('tenant.branding_updated', (int) $tenant->id, Tenant::class, (string) $tenant->id);

        return back()->with('status', 'Portal branding saved.');
    }
}
