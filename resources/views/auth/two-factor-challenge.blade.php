<x-layouts.app>
    <section class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-700">Additional verification</p>
        <h2 class="mt-2 text-2xl font-semibold">Verify it’s you</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Enter the current code from your authenticator app, or use one unused recovery code.</p>
        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-4">@csrf
            <label class="block text-sm font-medium" for="challenge-code">Authenticator or recovery code</label>
            <input id="challenge-code" class="w-full rounded-lg border border-slate-300 p-3 text-center text-lg tracking-[.3em]" name="code" autocomplete="one-time-code" autofocus required>
            <button class="w-full rounded-lg bg-indigo-700 px-4 py-3 text-sm font-semibold text-white">Continue</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm text-slate-600 underline">Sign out</button></form>
    </section>
</x-layouts.app>
