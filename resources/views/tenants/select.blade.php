<x-layouts.app>
    <section class="rounded-xl bg-white p-8 shadow-sm">
        <h1 class="mb-6 text-2xl font-semibold">Choose an ISP workspace</h1>
        @if ($memberships->isEmpty())<p>Your account has no active tenant memberships.</p>@else
            <form method="POST" action="{{ route('tenant.select.store') }}" class="space-y-3">
                @csrf
                @foreach ($memberships as $membership)
                    <button name="tenant_id" value="{{ $membership->tenant_id }}" class="block w-full rounded border p-4 text-left hover:bg-slate-50">
                        <strong>{{ $membership->tenant->name }}</strong><span class="ml-2 text-sm text-slate-500">{{ ucfirst($membership->role) }}</span>
                    </button>
                @endforeach
            </form>
        @endif
    </section>
</x-layouts.app>
