<x-layouts.app>
    <section class="mx-auto max-w-md rounded-xl bg-white p-8 shadow-sm">
        <h1 class="mb-6 text-2xl font-semibold">Sign in to Lumenix</h1>
        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf
            <label class="block">Email<input class="mt-1 w-full rounded border p-2" type="email" name="email" value="{{ old('email') }}" required autofocus></label>
            <label class="block">Password<input class="mt-1 w-full rounded border p-2" type="password" name="password" required></label>
            <p class="-mt-2 text-right text-sm"><a class="text-indigo-700 underline" href="{{ route('password.request') }}">Forgot password?</a></p>
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1"> Remember me</label>
            <button class="rounded bg-indigo-700 px-4 py-2 font-medium text-white">Sign in</button>
        </form>
        <p class="mt-6 border-t border-slate-100 pt-5 text-sm text-slate-600">New to Lumenix? <a class="font-semibold text-indigo-700 underline" href="{{ route('register') }}">Create your ISP workspace</a></p>
    </section>
</x-layouts.app>
