<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = Auth::user();
        if ($user?->two_factor_confirmed_at) {
            $request->session()->put('two_factor_passed', false);

            return redirect()->route('two-factor.challenge');
        }
        if ($user && $this->requiresTwoFactor($user)) {
            return redirect()->route('account.security')->with('warning', 'Set up an authenticator code before opening privileged staff pages.');
        }

        return redirect()->route('tenant.select');
    }

    private function requiresTwoFactor(\App\Models\User $user): bool
    {
        return $user->platformAdmin()->exists() || $user->tenantMemberships()
            ->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
