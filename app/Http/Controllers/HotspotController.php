<?php

namespace App\Http\Controllers;

use App\Models\HotspotVoucher;
use App\Models\Plan;
use App\Models\Router;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class HotspotController extends Controller
{
    public function index(): View
    {
        return view('admin.workspace.hotspot', [
            'routers' => Router::query()->orderBy('name')->get(),
            'plans' => Plan::query()->where('active', true)->orderBy('name')->get(),
            'vouchers' => HotspotVoucher::query()->with(['plan', 'router'])->latest()->paginate(25),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManager($request);
        [$voucher, $password] = $this->issue($request, $audit);

        return back()->with('issued_voucher', ['username' => $voucher->username, 'password' => $password]);
    }

    public function apiStore(Request $request, AuditLogger $audit): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManager($request);
        [$voucher, $password] = $this->issue($request, $audit);

        return response()->json(['data' => [
            'id' => $voucher->id, 'username' => $voucher->username, 'password' => $password,
            'status' => $voucher->status, 'plan' => $voucher->plan()->first(['id', 'name']),
            'credential_note' => 'Copy the password now; it is only returned once.',
        ]], 201);
    }

    /** @return array{HotspotVoucher, string} */
    private function issue(Request $request, AuditLogger $audit): array
    {
        $tenantId = app(\App\Domain\Tenancy\TenantContext::class)->id();
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('tenant_id', $tenantId)->where('active', true)],
            'router_id' => ['nullable', 'integer', Rule::exists('routers', 'id')->where('tenant_id', $tenantId)],
        ]);
        do {
            $username = 'HS'.strtoupper(Str::random(10));
        } while (HotspotVoucher::query()->where('username', $username)->exists());
        $password = Str::password(12);
        $voucher = DB::transaction(function () use ($data, $username, $password): HotspotVoucher {
            $voucher = HotspotVoucher::query()->create([...$data, 'username' => $username, 'password' => $password]);
            $plan = Plan::query()->findOrFail($voucher->plan_id);
            DB::table('radcheck')->insert(['username' => $username, 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $password]);
            DB::table('radreply')->insert(['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit', 'op' => '=', 'value' => $plan->speed_up_kbps.'k/'.$plan->speed_down_kbps.'k']);

            return $voucher;
        });
        $audit->record('hotspot.voucher_issued', (int) $voucher->tenant_id, HotspotVoucher::class, (string) $voucher->id, ['plan_id' => $voucher->plan_id, 'router_id' => $voucher->router_id]);

        return [$voucher, $password];
    }

    public function revoke(Request $request, string $voucher, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManager($request);
        $voucher = HotspotVoucher::query()->findOrFail($voucher);
        abort_unless($voucher->status === 'active', 409, 'This voucher has already been revoked.');
        DB::transaction(function () use ($voucher): void {
            DB::table('radcheck')->where('username', $voucher->username)->delete();
            DB::table('radcheck')->insert(['username' => $voucher->username, 'attribute' => 'Auth-Type', 'op' => ':=', 'value' => 'Reject']);
            $voucher->update(['status' => 'revoked']);
        });
        $audit->record('hotspot.voucher_revoked', (int) $voucher->tenant_id, HotspotVoucher::class, (string) $voucher->id);

        return back()->with('status', 'Voucher revoked and new RADIUS authentication blocked.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless(in_array($request->attributes->get('tenant_membership')?->role, ['owner', 'admin'], true), 403);
    }
}
