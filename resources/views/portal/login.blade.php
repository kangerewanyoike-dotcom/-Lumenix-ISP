<x-portal-guest-layout>
    <section class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-indigo-950/5 sm:p-8">
        <div class="mb-7">
            <div class="w-fit rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-800" style="width:fit-content;border-radius:9999px;background:#e0e7ff;padding:4px 12px;color:#3730a3;font-size:12px;font-weight:600;letter-spacing:.05em">Welcome back</div>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight">Sign in to your account</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Use the portal email and password provided by your internet service provider.</p>
        </div>
        <form class="space-y-5" method="POST" action="{{ route('portal.login.store') }}">
            @csrf
            <label class="block text-sm font-medium text-slate-800" for="portal-email">Email address
                <input id="portal-email" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-100 sm:text-sm" type="email" name="email" autocomplete="username" inputmode="email" maxlength="254" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
            </label>
            <label class="block text-sm font-medium text-slate-800" for="portal-password">Password
                <input id="portal-password" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-100 sm:text-sm" type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="flex w-full items-center justify-center rounded-lg bg-indigo-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-800 focus:outline-none focus:ring-4 focus:ring-indigo-200" type="submit">Sign in securely</button>
        </form>
        <p class="mt-6 border-t border-slate-100 pt-5 text-center text-xs leading-5 text-slate-500">Need help signing in? Contact your internet service provider.</p>
    </section>
</x-portal-guest-layout>
