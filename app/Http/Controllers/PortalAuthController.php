<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PortalAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:128']]);
        $account = CustomerPortalAccount::withoutGlobalScopes()->where('email', Str::lower($data['email']))->where('active', true)->first();
        $valid = Hash::check($data['password'], $account?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        $tenant = $account ? Tenant::query()->find($account->tenant_id) : null;
        $customer = $account ? Customer::withoutGlobalScopes()->whereNull('deleted_at')->find($account->customer_id) : null;
        if (! $valid || ! $tenant || $tenant->status !== 'active' || ! $customer) {
            return back()->withErrors(['email' => 'These portal credentials do not match our records.'])->onlyInput('email');
        }
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('customer_portal_account_id', $account->id);
        $account->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('status', 'You have signed out.');
    }
}
