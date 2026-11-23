<x-layouts.app>
    <section class="mx-auto max-w-md rounded-xl bg-white p-8 shadow-sm">
        <h2 class="mb-2 text-2xl font-semibold">Reset your password</h2>
        <p class="mb-6 text-sm text-slate-600">Enter your work email and we’ll send a secure password reset link if an account exists.</p>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <label class="block text-sm font-medium">Work email<input class="mt-1 w-full rounded border p-3" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
            <button class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-semibold text-white">Send reset link</button>
        </form>
        <p class="mt-5 text-sm"><a class="text-indigo-700 underline" href="{{ route('login') }}">Back to sign in</a></p>
    </section>
</x-layouts.app>
