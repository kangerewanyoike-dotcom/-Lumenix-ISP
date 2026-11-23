<?php

namespace App\Http\Controllers;

use App\Models\PlatformSupportTicket;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantSupportController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (int) $request->attributes->get('tenant_membership')->tenant_id;

        return view('admin.support.index', [
            'tickets' => PlatformSupportTicket::query()->where('tenant_id', $tenantId)->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);
        $tenantId = (int) $request->attributes->get('tenant_membership')->tenant_id;
        $ticket = PlatformSupportTicket::query()->create([...$data, 'tenant_id' => $tenantId, 'status' => 'open', 'created_by' => $request->user()->id]);
        $audit->record('tenant.support_ticket_created', $tenantId, PlatformSupportTicket::class, (string) $ticket->id);

        return back()->with('status', 'Support request sent to Lumenix.');
    }
}
