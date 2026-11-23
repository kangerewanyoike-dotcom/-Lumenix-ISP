<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SecurityController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.security', [
            'enabled' => (bool) $request->user()->two_factor_confirmed_at,
            'setupSecret' => $request->session()->get('two_factor_setup_secret'),
        ]);
    }

    public function begin(Request $request, Totp $totp): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->two_factor_confirmed_at, 409, 'An authenticator is already enabled for this account.');
        $data = $request->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $request->session()->put('two_factor_setup_secret', $totp->secret());

        return back()->with('status', 'Scan the authenticator secret, then enter its current six-digit code to confirm setup.');
    }

    public function confirm(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = (string) $request->session()->get('two_factor_setup_secret', '');
        if ($secret === '' || ! $totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid or expired.']);
        }

        $user = $request->user();
        $recoveryCodes = collect(range(1, 10))->map(fn (): string => strtoupper(Str::random(4).bin2hex(random_bytes(3))))->all();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(Hash::make(...), $recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $audit->record('staff.two_factor_enabled', null, User::class, (string) $user->id);

        $request->session()->forget('two_factor_setup_secret');
        $request->session()->put('two_factor_passed', true);

        return redirect()->route($user->platformAdmin()->exists() ? 'platform.dashboard' : 'tenant.select')
            ->with('recovery_codes', $recoveryCodes)
            ->with('status', 'Authenticator verification is enabled. Store your recovery codes somewhere safe.');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('account.security');
        }
        if ($request->session()->boolean('two_factor_passed')) {
            return redirect()->route($user->platformAdmin()->exists() ? 'platform.dashboard' : 'tenant.select');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'min:6', 'max:64']]);
        $user = $request->user();
        $valid = $totp->verify((string) $user->two_factor_secret, $data['code']);
        $usedRecoveryCode = false;

        if (! $valid) {
            $recoveryCodes = $user->two_factor_recovery_codes ?? [];
            foreach ($recoveryCodes as $index => $hash) {
                if (Hash::check(strtoupper(trim($data['code'])), $hash)) {
                    unset($recoveryCodes[$index]);
                    $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();
                    $valid = true;
                    $usedRecoveryCode = true;
                    break;
                }
            }
        }

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'The authenticator or recovery code is invalid.']);
        }

        if ($usedRecoveryCode) {
            $audit->record('staff.two_factor_recovery_used', null, User::class, (string) $user->id);
        }
        $request->session()->put('two_factor_passed', true);
        $request->session()->regenerate();

        return redirect()->route($user->platformAdmin()->exists() ? 'platform.dashboard' : 'tenant.select');
    }
}
