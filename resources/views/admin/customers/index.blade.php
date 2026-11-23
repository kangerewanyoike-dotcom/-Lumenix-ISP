<x-layouts.app>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="mb-2 text-xs font-bold uppercase tracking-[.14em] text-indigo-700">Subscriber operations</p><h2 class="text-3xl font-semibold">Customers</h2><p class="mt-2 text-sm text-slate-600">Find accounts, check service status, and follow balances that need attention.</p></div>
        <a class="rounded-xl bg-indigo-700 px-4 py-3 text-sm font-semibold text-white" href="{{ route('admin.customers.create') }}">Add customer</a>
    </div>

    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">All customers</p><p class="mt-2 text-2xl font-semibold">{{ number_format($summary['total']) }}</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active service</p><p class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['active']) }}</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Suspended</p><p class="mt-2 text-2xl font-semibold {{ $summary['suspended'] ? 'text-amber-700' : '' }}">{{ number_format($summary['suspended']) }}</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">RADIUS online</p><p class="mt-2 text-2xl font-semibold text-indigo-700">{{ number_format($summary['online']) }}</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding</p><p class="mt-2 text-xl font-semibold">{{ $currency }} {{ $money($summary['outstanding_minor']) }}</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Overdue invoices</p><p class="mt-2 text-2xl font-semibold {{ $summary['overdue'] ? 'text-rose-700' : '' }}">{{ number_format($summary['overdue']) }}</p></article>
    </section>

    <form method="GET" action="{{ route('admin.customers.index') }}" class="mt-6 rounded-xl bg-white p-4 sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <label class="block text-xs font-semibold text-slate-600 xl:col-span-2">Search accounts
                <input class="mt-1.5 w-full rounded border p-3 text-sm" name="q" value="{{ request('q') }}" placeholder="Name, account, phone, email, or PPPoE username">
            </label>
            <label class="block text-xs font-semibold text-slate-600">Service status
                <select class="mt-1.5 w-full rounded border p-3 text-sm" name="status"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select>
            </label>
            <label class="block text-xs font-semibold text-slate-600">Connection
                <select class="mt-1.5 w-full rounded border p-3 text-sm" name="connection"><option value="">Any connection</option><option value="online" @selected(request('connection') === 'online')>RADIUS online</option><option value="offline" @selected(request('connection') === 'offline')>RADIUS offline</option></select>
            </label>
            <label class="block text-xs font-semibold text-slate-600">Plan
                <select class="mt-1.5 w-full rounded border p-3 text-sm" name="plan_id"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>@endforeach</select>
            </label>
            <label class="block text-xs font-semibold text-slate-600">Balance
                <select class="mt-1.5 w-full rounded border p-3 text-sm" name="balance"><option value="">Any balance</option><option value="outstanding" @selected(request('balance') === 'outstanding')>Has outstanding</option><option value="overdue" @selected(request('balance') === 'overdue')>Has overdue invoice</option></select>
            </label>
        </div>
        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <p class="text-xs text-slate-500">Showing {{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }} of {{ number_format($customers->total()) }} customers</p>
            <div class="flex items-center gap-4"><a class="text-sm text-slate-600 underline" href="{{ route('admin.customers.index') }}">Clear filters</a><button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Apply filters</button></div>
        </div>
    </form>

    <section class="mt-5 overflow-hidden rounded-xl bg-white">
        <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm">
            <thead><tr class="border-b border-slate-100"><th class="px-5 py-3">Customer</th><th>Plan</th><th>Service</th><th>Connection</th><th>Balance due</th><th>Last payment</th><th class="px-5 py-3 text-right">Account</th></tr></thead>
            <tbody>
                @forelse($customers as $customer)
                    @php($balance = (int) ($customer->balance_due_minor ?? 0))
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/70">
                        <td class="px-5 py-4"><a class="font-semibold text-slate-900 hover:text-indigo-700" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a><span class="mt-1 block text-xs text-slate-500">{{ $customer->account_number }} · {{ $customer->phone }}</span></td>
                        <td><span class="font-medium">{{ $customer->plan?->name ?? 'No plan' }}</span><span class="mt-1 block text-xs text-slate-500">{{ $customer->pppoe_username }}</span></td>
                        <td><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $customer->status === 'active', 'bg-amber-50 text-amber-800' => $customer->status !== 'active'])>{{ ucfirst($customer->status) }}</span></td>
                        <td><span class="inline-flex items-center gap-2 text-xs font-medium {{ $customer->active_session_count ? 'text-emerald-700' : 'text-slate-500' }}"><span class="h-2 w-2 rounded-full {{ $customer->active_session_count ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>{{ $customer->active_session_count ? 'Online' : 'Offline' }}</span></td>
                        <td><span class="font-semibold {{ $balance > 0 ? 'text-rose-700' : 'text-slate-700' }}">{{ $currency }} {{ $money($balance) }}</span>@if($balance > 0)<span class="mt-1 block text-[11px] text-slate-500">Outstanding</span>@endif</td>
                        <td class="text-xs text-slate-600">{{ $customer->last_payment_at ? \Illuminate\Support\Carbon::parse($customer->last_payment_at)->format('d M Y') : 'No payment recorded' }}</td>
                        <td class="px-5 py-4 text-right"><a class="font-semibold text-indigo-700 underline" href="{{ route('admin.customers.show', $customer) }}">View account</a></td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-12 text-center" colspan="7"><p class="font-semibold text-slate-700">No customers match these filters</p><p class="mt-1 text-sm text-slate-500">Try a different search or clear the filters.</p></td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $customers->links() }}</div>
    </section>
</x-layouts.app>
