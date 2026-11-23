<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Router;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class RouterTelemetryController extends Controller
{
    public function store(Request $request, string $router, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        $device = Router::withoutGlobalScopes()->findOrFail($router);
        abort_unless(filled($device->radius_secret), 401);
        $timestamp = (string) $request->header('X-Router-Timestamp', '');
        abort_unless(ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300, 401, 'Telemetry timestamp is invalid or expired.');
        $signature = (string) $request->header('X-Router-Signature', '');
        $expected = hash_hmac('sha256', $timestamp."\n".$request->getContent(), $device->radius_secret);
        abort_unless(hash_equals($expected, $signature), 401, 'Telemetry signature is invalid.');

        $data = Validator::make($request->all(), [
            'cpu_percent' => ['required', 'numeric', 'between:0,100'],
            'memory_percent' => ['required', 'numeric', 'between:0,100'],
            'uptime_seconds' => ['required', 'integer', 'min:0'],
            'download_bps' => ['required', 'integer', 'min:0'],
            'upload_bps' => ['required', 'integer', 'min:0'],
        ])->validate();

        $context->set(Tenant::query()->findOrFail($device->tenant_id));
        try {
            $device->update([...$data, 'status' => 'active', 'last_seen_at' => now(), 'telemetry_updated_at' => now()]);
            $audit->record('router.telemetry_received', (int) $device->tenant_id, Router::class, (string) $device->id);
        } finally {
            $context->clear();
        }

        return response()->json(['data' => ['router_id' => $device->id, 'received_at' => $device->telemetry_updated_at]]);
    }
}
