<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $privileged = $user->platformAdmin()->exists() || $user->tenantMemberships()
            ->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists();
        if (! $privileged) {
            return $next($request);
        }

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('account.security')->with('warning', 'Set up an authenticator code to continue to privileged staff pages.');
        }
        if (! (bool) $request->session()->get('two_factor_passed', false)) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
