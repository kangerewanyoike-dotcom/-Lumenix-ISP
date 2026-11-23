<x-layouts.app>
    <section class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-700">Account protection</p>
        <h2 class="mt-2 text-2xl font-semibold">Authenticator security</h2>
        @if($enabled)
            <p class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-900">Authenticator verification is enabled. You will be asked for a code after signing in. Use a recovery code if you lose access to your authenticator; each recovery code works once.</p>
        @else
            <p class="mt-3 text-sm leading-6 text-slate-600">Platform admins and ISP owners/admins must enroll an authenticator before opening privileged pages. Add the setup key to an authenticator app, then confirm with its current six-digit code.</p>
            @if(! $setupSecret)
                <form method="POST" action="{{ route('account.security.two-factor.setup') }}" class="mt-6 space-y-4">@csrf
                    <label class="block text-sm font-medium">Current password<input class="mt-1 w-full rounded-lg border border-slate-300 p-3" type="password" name="current_password" autocomplete="current-password" required></label>
                    <button class="rounded-lg bg-indigo-700 px-4 py-3 text-sm font-semibold text-white">Begin authenticator setup</button>
                </form>
            @else
                <div class="mt-6 rounded-xl bg-slate-50 p-5">
                    <h3 class="font-semibold">1. Add Lumenix to your authenticator app</h3>
                    <p class="mt-2 text-sm text-slate-600">Enter this setup key manually. Keep it private; it can generate sign-in codes for this account.</p>
                    <code class="mt-3 block break-all rounded-lg bg-white p-3 text-sm tracking-wider">{{ $setupSecret }}</code>
                    <p class="mt-4 text-sm font-semibold">2. Confirm setup</p>
                    <form method="POST" action="{{ route('account.security.two-factor.confirm') }}" class="mt-2 flex flex-wrap gap-3">@csrf
                        <label class="sr-only" for="setup-code">Current six-digit authenticator code</label>
                        <input id="setup-code" class="min-w-48 rounded-lg border border-slate-300 p-3" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
                        <button class="rounded-lg bg-indigo-700 px-4 py-3 text-sm font-semibold text-white">Confirm and enable</button>
                    </form>
                </div>
            @endif
        @endif
    </section>
</x-layouts.app>
