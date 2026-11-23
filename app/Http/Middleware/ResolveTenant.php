<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $membership = $user->tenantMemberships()
            ->with('tenant')
            ->where('tenant_id', $request->session()->get('tenant_id'))
            ->where('status', 'active')
            ->first();

        abort_unless($membership && $membership->tenant->status === 'active', 403);

        $context = app(TenantContext::class);
        $context->set($membership->tenant);
        $request->attributes->set('tenant_membership', $membership);

        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
