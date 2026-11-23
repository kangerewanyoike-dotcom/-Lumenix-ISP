<x-layouts.app>
    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="mb-1 text-xs font-bold uppercase tracking-[.14em] text-indigo-700">{{ $customer->account_number }}</p><h2 class="text-3xl font-semibold">{{ $customer->name }}</h2></div><div class="flex flex-wrap items-center gap-4"><span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $customer->status === 'active', 'bg-amber-50 text-amber-800' => $customer->status !== 'active'])>{{ ucfirst($customer->status) }} service</span>@if($customer->status === 'suspended' && $customer->subscription_status === 'active')<form method="POST" action="{{ route('admin.customers.reactivate', $customer) }}">@csrf<button class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Restore service</button></form>@endif<a class="text-sm font-medium text-slate-600 underline" href="{{ route('admin.customers.export', $customer) }}">Export data</a><a class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white" href="{{ route('admin.customers.edit', $customer) }}">Edit account</a></div></div>
    <p class="mt-2 text-sm text-slate-600">{{ $customer->plan?->name ?? 'No plan assigned' }} · PPPoE {{ $customer->pppoe_username }} · Subscription {{ ucfirst($customer->subscription_status ?? 'active') }} · Provisioning {{ ucfirst($customer->provisioning_status ?? 'pending') }}</p>

    @php($lastPayment = $recentPayments->first())
    @php($usageGb = (($usage->input_octets ?? 0) + ($usage->output_octets ?? 0)) / 1073741824)
    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding balance</p><p class="mt-2 text-xl font-semibold">{{ $currency }} {{ $money($outstandingMinor) }}</p><p class="mt-1 text-xs text-slate-500">Across all unpaid invoices</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Past due</p><p class="mt-2 text-xl font-semibold {{ $overdueMinor ? 'text-rose-700' : 'text-slate-800' }}">{{ $currency }} {{ $money($overdueMinor) }}</p><p class="mt-1 text-xs text-slate-500">Invoices past their due date</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Connection now</p><p class="mt-2 text-xl font-semibold {{ $activeSessions->isNotEmpty() ? 'text-emerald-700' : 'text-slate-700' }}">{{ $activeSessions->isNotEmpty() ? 'Online' : 'Offline' }}</p><p class="mt-1 text-xs text-slate-500">Based on RADIUS accounting</p></article>
        <article class="rounded-xl bg-white p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Usage this cycle</p><p class="mt-2 text-xl font-semibold">{{ number_format($usageGb, 2) }} GB</p><p class="mt-1 text-xs text-slate-500">Upload and download combined</p></article>
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-[1fr_1.35fr]">
        <section class="rounded-xl bg-white p-5">
            <h3 class="font-semibold">Account details</h3>
            <dl class="mt-4 grid grid-cols-[minmax(110px,.7fr)_1.3fr] gap-x-3 gap-y-3 text-sm"><dt class="text-slate-500">Phone</dt><dd>{{ $customer->phone }}</dd><dt class="text-slate-500">Email</dt><dd class="break-all">{{ $customer->email ?? '—' }}</dd><dt class="text-slate-500">Address</dt><dd>{{ $customer->address ?? '—' }}</dd><dt class="text-slate-500">Service username</dt><dd>{{ $customer->pppoe_username }}</dd><dt class="text-slate-500">Service password</dt><dd>Saved securely (hidden)</dd><dt class="text-slate-500">Billing day</dt><dd>{{ $customer->billing_day }}</dd><dt class="text-slate-500">Available credit</dt><dd>{{ $currency }} {{ $money($creditBalanceMinor) }}</dd><dt class="text-slate-500">Last payment</dt><dd>{{ $lastPayment?->paid_at?->format('d M Y, H:i') ?? 'No payment recorded' }}</dd><dt class="text-slate-500">Paid amount</dt><dd>{{ $lastPayment ? $currency.' '.$money($lastPayment->amount_minor) : '—' }}</dd></dl>
        </section>
        <section class="rounded-xl bg-white p-5">
            <div class="flex items-center justify-between gap-3"><div><h3 class="font-semibold">Current RADIUS sessions</h3><p class="mt-1 text-xs text-slate-500">Accounting can take up to a minute to update.</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $activeSessions->count() }}</span></div>
            @forelse($activeSessions as $session)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-100 bg-emerald-50/50 p-3"><div><p class="text-sm font-semibold text-emerald-900">Active session</p><p class="mt-1 text-xs text-slate-600">Started {{ $session->started_at?->format('d M Y, H:i') ?? 'time unavailable' }}</p></div><p class="text-xs text-slate-600">NAS {{ $session->nas_ip_address ?? 'not reported' }}</p></div>
            @empty
                <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No active RADIUS session is currently recorded.</p>
            @endforelse
        </section>
    </div>

    <div class="mt-6 grid gap-5 xl:grid-cols-2">
        <section class="overflow-hidden rounded-xl bg-white">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold">Recent invoices</h3></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[500px] text-left text-sm"><thead><tr class="border-b border-slate-100"><th class="px-5 py-3">Invoice</th><th>Status</th><th>Due date</th><th class="px-5 py-3 text-right">Balance</th></tr></thead><tbody>
                @forelse($recentInvoices as $invoice)<tr class="border-b border-slate-50 last:border-0"><td class="px-5 py-3"><a class="font-medium text-indigo-700 underline" href="{{ route('admin.billing.show', $invoice) }}">{{ $invoice->number }}</a><span class="mt-1 block text-xs text-slate-500">Issued {{ $invoice->issued_at?->format('d M Y') ?? '—' }}</span></td><td>{{ ucfirst($invoice->status) }}</td><td>{{ $invoice->due_at?->format('d M Y') ?? '—' }}</td><td class="px-5 py-3 text-right">{{ $currency }} {{ $money($invoice->balance_minor) }}</td></tr>
                @empty<tr><td class="px-5 py-6 text-sm text-slate-500" colspan="4">No invoices yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>
        <section class="overflow-hidden rounded-xl bg-white">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold">Recent payments</h3></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[440px] text-left text-sm"><thead><tr class="border-b border-slate-100"><th class="px-5 py-3">Date</th><th>Receipt</th><th>Status</th><th class="px-5 py-3 text-right">Amount</th></tr></thead><tbody>
                @forelse($recentPayments as $payment)<tr class="border-b border-slate-50 last:border-0"><td class="px-5 py-3">{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td><td>{{ $payment->receipt }}</td><td>{{ ucfirst($payment->status) }}</td><td class="px-5 py-3 text-right">{{ $payment->currency }} {{ $money($payment->amount_minor) }}</td></tr>
                @empty<tr><td class="px-5 py-6 text-sm text-slate-500" colspan="4">No payments recorded.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>

    <section class="mt-6 overflow-hidden rounded-xl bg-white">
        <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold">Recent connection history</h3></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead><tr class="border-b border-slate-100"><th class="px-5 py-3">Started</th><th>Ended</th><th>NAS</th><th>Duration</th><th class="px-5 py-3 text-right">Data used</th></tr></thead><tbody>
            @forelse($recentSessions as $session)
                @php($sessionBytes = $session->input_octets + $session->output_octets)
                <tr class="border-b border-slate-50 last:border-0"><td class="px-5 py-3">{{ $session->started_at?->format('d M Y, H:i') ?? '—' }}</td><td>{{ $session->stopped_at?->format('d M Y, H:i') ?? 'Active' }}</td><td>{{ $session->nas_ip_address ?? '—' }}</td><td>{{ $session->started_at && $session->stopped_at ? $session->started_at->diffForHumans($session->stopped_at, true) : ($session->stopped_at ? '—' : 'In progress') }}</td><td class="px-5 py-3 text-right">{{ number_format($sessionBytes / 1073741824, 2) }} GB</td></tr>
            @empty<tr><td class="px-5 py-6 text-sm text-slate-500" colspan="5">No RADIUS accounting sessions have been ingested for this customer.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    @if(in_array(request()->attributes->get('tenant_membership')?->role, ['owner', 'admin'], true))
        <form method="POST" action="{{ route('admin.customers.portal-account', $customer) }}" class="mt-6 max-w-2xl space-y-3 rounded bg-white p-5">@csrf @method('PUT')<h2 class="font-semibold">Customer portal access</h2><p class="text-sm text-slate-600">Portal accounts are separate from staff accounts. Login: <a class="underline" href="{{ route('portal.login') }}">{{ route('portal.login') }}</a>. Use a secure channel to share credentials.</p><label class="block">Portal email<input class="mt-1 w-full rounded border p-2" type="email" name="email" maxlength="254" value="{{ old('email', $customer->portalAccount?->email ?? $customer->email) }}" required></label><label class="block">{{ $customer->portalAccount ? 'New password (leave blank to keep current)' : 'Initial password (minimum 12 characters)' }}<input class="mt-1 w-full rounded border p-2" type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" {{ $customer->portalAccount ? '' : 'required' }}></label><label class="block">Confirm password<input class="mt-1 w-full rounded border p-2" type="password" name="password_confirmation" autocomplete="new-password" {{ $customer->portalAccount ? '' : 'required' }}></label><label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked($customer->portalAccount?->active ?? true)> Portal login enabled</label><button class="rounded bg-indigo-700 px-4 py-2 text-white">Save portal access</button></form>
        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.billing.customers.plan', $customer) }}" class="space-y-3 rounded bg-white p-5">@csrf
                <h2 class="font-semibold">Change plan</h2>
                <label class="block">New {{ $customer->plan?->billing_period ?? 'monthly' }} plan<select class="mt-1 w-full rounded border p-2" name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} — {{ $currency }} {{ $money($plan->price_minor) }}</option>@endforeach</select></label>
                <div class="grid grid-cols-3 gap-2"><label>Period start<input class="mt-1 w-full rounded border p-2" type="date" name="period_start" value="{{ $periodStart->toDateString() }}" required></label><label>Period end<input class="mt-1 w-full rounded border p-2" type="date" name="period_end" value="{{ $periodEnd->toDateString() }}" required></label><label>Effective date<input class="mt-1 w-full rounded border p-2" type="date" name="effective_at" max="{{ $effectiveDate->toDateString() }}" value="{{ $effectiveDate->toDateString() }}" required></label></div>
                <input type="hidden" name="idempotency_key" value="{{ $billingKey }}"><button class="rounded bg-indigo-700 px-4 py-2 text-white">Apply plan change</button>
            </form>
            @if($customer->plan?->billing_period === 'monthly')
                <form method="POST" action="{{ route('admin.billing.customers.billing-day', $customer) }}" class="space-y-3 rounded bg-white p-5" id="billing-day-form">@csrf
                    <h2 class="font-semibold">Change billing day</h2>
                    <label class="block">New billing day (1–28)<input id="new-billing-day" class="mt-1 w-full rounded border p-2" type="number" name="billing_day" min="1" max="28" value="{{ $customer->billing_day }}" required><span class="text-xs text-slate-500">The transition ends the day before the next billing date.</span></label>
                    <div class="grid grid-cols-3 gap-2"><label>Cycle start<input class="mt-1 w-full rounded border p-2" type="date" name="cycle_start" value="{{ $periodStart->toDateString() }}" required></label><label>Current end<input id="current-cycle-end" class="mt-1 w-full rounded border p-2" type="date" name="old_cycle_end" value="{{ $periodEnd->toDateString() }}" required></label><label>New end<input id="new-cycle-end" class="mt-1 w-full rounded border p-2" type="date" name="new_cycle_end" value="{{ $periodEnd->toDateString() }}" required></label></div>
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button class="rounded bg-indigo-700 px-4 py-2 text-white">Apply billing-day change</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.billing.customers.credits', $customer) }}" class="space-y-3 rounded bg-white p-5">@csrf
                <h2 class="font-semibold">Grant customer credit</h2><label class="block">Amount (minor units)<input class="mt-1 w-full rounded border p-2" type="number" name="amount_minor" min="1" required></label><label class="block">Reason<input class="mt-1 w-full rounded border p-2" name="description" maxlength="200" required></label>
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button class="rounded bg-indigo-700 px-4 py-2 text-white">Add credit</button>
            </form>
        </div>
        @if($customer->plan?->billing_period === 'monthly')
            <script>
                document.getElementById('new-billing-day')?.addEventListener('change', function () {
                    const endField = document.getElementById('current-cycle-end');
                    const dueDate = new Date(endField.value + 'T00:00:00');
                    dueDate.setDate(1);
                    dueDate.setDate(Number(this.value));
                    if (dueDate <= new Date(endField.value + 'T00:00:00')) dueDate.setMonth(dueDate.getMonth() + 1);
                    dueDate.setDate(dueDate.getDate() - 1);
                    const pad = value => String(value).padStart(2, '0');
                    document.getElementById('new-cycle-end').value = `${dueDate.getFullYear()}-${pad(dueDate.getMonth() + 1)}-${pad(dueDate.getDate())}`;
                });
            </script>
        @endif
    @endif
</x-layouts.app>
