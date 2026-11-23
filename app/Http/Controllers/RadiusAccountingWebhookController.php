<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\HotspotVoucher;
use App\Models\RadiusAccountingSession;
use App\Models\Router;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RadiusAccountingWebhookController extends Controller
{
    public function store(Request $request, TenantContext $context): JsonResponse
    {
        $timestamp = (string) $request->header('X-Radius-Timestamp', '');
        abort_unless(ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300, 401, 'Accounting timestamp is invalid or expired.');
        $data = $request->validate([
            'nas_ip_address' => ['required', 'ip'], 'acct_unique_id' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:64'], 'status_type' => ['required', 'in:Start,Interim-Update,Stop'],
            'started_at' => ['nullable', 'date'], 'session_seconds' => ['nullable', 'integer', 'min:0'],
            'input_octets' => ['nullable', 'integer', 'min:0'], 'output_octets' => ['nullable', 'integer', 'min:0'],
            'stopped_at' => ['nullable', 'date'],
        ]);
        $signature = (string) $request->header('X-Radius-Signature', '');
        $routers = Router::withoutGlobalScopes()->where('endpoint', $data['nas_ip_address'])->whereNotNull('radius_secret')->get();
        $router = $routers->first(function (Router $candidate) use ($timestamp, $signature, $request): bool {
            $expected = hash_hmac('sha256', $timestamp."\n".$request->getContent(), $candidate->radius_secret);

            return hash_equals($expected, $signature);
        });
        abort_unless($router, 401, 'No registered router matched this signed accounting request.');

        $customer = DB::table('customers')->where('tenant_id', $router->tenant_id)->where('pppoe_username', $data['username'])->whereNull('deleted_at')->first(['id']);
        $voucher = $customer ? null : DB::table('hotspot_vouchers')->where('tenant_id', $router->tenant_id)->where('username', $data['username'])->first(['id']);
        abort_unless($customer || $voucher, 404, 'Subscriber username is not registered to this router tenant.');
        $context->set(Tenant::query()->findOrFail($router->tenant_id));
        try {
            DB::transaction(function () use ($data, $customer): void {
                $existing = RadiusAccountingSession::query()->where('acct_unique_id', $data['acct_unique_id'])->lockForUpdate()->first();
                if ($existing?->stopped_at && $data['status_type'] !== 'Stop') {
                    return;
                }
                RadiusAccountingSession::query()->updateOrCreate(
                    ['acct_unique_id' => $data['acct_unique_id']],
                    [
                        'customer_id' => $customer?->id, 'username' => $data['username'],
                        'access_type' => $customer ? 'pppoe' : 'hotspot', 'nas_ip_address' => $data['nas_ip_address'],
                        'started_at' => $data['started_at'] ?? $existing?->started_at ?? now(),
                        'updated_at_radius' => now(), 'stopped_at' => $data['status_type'] === 'Stop' ? ($data['stopped_at'] ?? now()) : null,
                        'session_seconds' => $data['session_seconds'] ?? $existing?->session_seconds ?? 0,
                        'input_octets' => $data['input_octets'] ?? $existing?->input_octets ?? 0,
                        'output_octets' => $data['output_octets'] ?? $existing?->output_octets ?? 0,
                    ],
                );
            }, 3);
        } finally {
            $context->clear();
        }

        return response()->json(['accepted' => true], 202);
    }
}
