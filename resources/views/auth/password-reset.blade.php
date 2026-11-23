<x-layouts.app>
    <section class="mx-auto max-w-md rounded-xl bg-white p-8 shadow-sm">
        <h2 class="mb-2 text-2xl font-semibold">Choose a new password</h2>
        <p class="mb-6 text-sm text-slate-600">Use at least 12 characters. You’ll sign in with this password after saving.</p>
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block text-sm font-medium">Work email<input class="mt-1 w-full rounded border p-3" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required></label>
            <label class="block text-sm font-medium">New password<input class="mt-1 w-full rounded border p-3" type="password" name="password" minlength="12" autocomplete="new-password" required></label>
            <label class="block text-sm font-medium">Confirm new password<input class="mt-1 w-full rounded border p-3" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
            <button class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-semibold text-white">Save new password</button>
        </form>
    </section>
</x-layouts.app>
