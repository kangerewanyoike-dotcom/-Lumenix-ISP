<x-layouts.app>
<section class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-semibold">Lumenix platform billing</h1><p class="mt-1 text-sm text-slate-600">SaaS tier pricing, monthly subscriber snapshots, and invoices owed to Lumenix.</p></div><form method="POST" action="{{ route('platform.billing.generate') }}" class="flex items-end gap-2">@csrf<label class="text-xs font-semibold text-slate-600">Invoice period<input type="month" name="period" value="{{ now()->subMonthNoOverflow()->format('Y-m') }}" class="mt-1 block rounded border p-2" required></label><button class="rounded bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">Generate invoices</button></form></div>
    <div class="space-y-4">
        @foreach($tenants as $tenant)
            <article class="rounded-xl bg-white p-5 shadow-sm"><h2 class="mb-3 font-semibold">{{ $tenant->name }} <span class="text-sm font-normal text-slate-500">({{ $tenant->status }})</span></h2>
                <form method="POST" action="{{ route('platform.billing.update', $tenant) }}" class="flex flex-wrap items-end gap-3">@csrf @method('PUT')
                    <label class="text-sm">SaaS plan<select name="platform_plan_id" class="mt-1 block rounded border p-2"><option value="">Legacy per-subscriber</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($tenant->platformSubscription?->platform_plan_id === $plan->id)>{{ $plan->name }} · {{ $plan->currency }} {{ number_format(intdiv($plan->monthly_price_minor, 100)) }} monthly</option>@endforeach</select></label>
                    <label class="text-sm">Overage per subscriber (minor units)<input type="number" min="0" name="price_per_subscriber_minor" value="{{ $tenant->platformSubscription?->price_per_subscriber_minor ?? 0 }}" class="mt-1 block rounded border p-2" required><span class="block text-xs text-slate-500">Charged above the plan’s included subscriber count.</span></label>
                    <label class="text-sm">Currency<input name="currency" maxlength="3" value="{{ $tenant->platformSubscription?->currency ?? $tenant->currency }}" class="mt-1 block w-24 rounded border p-2" required></label>
                    <label class="text-sm">Billing day<input type="number" min="1" max="28" name="billing_day" value="{{ $tenant->platformSubscription?->billing_day ?? 1 }}" class="mt-1 block w-24 rounded border p-2" required></label>
                    <label class="text-sm">Status<select name="status" class="mt-1 block rounded border p-2"><option value="active" @selected(($tenant->platformSubscription?->status ?? 'active') === 'active')>Active</option><option value="paused" @selected($tenant->platformSubscription?->status === 'paused')>Paused</option></select></label>
                    <button class="rounded bg-indigo-700 px-4 py-2 text-white">Save rate</button>
                </form>
            </article>
        @endforeach
    </div>
    <div class="overflow-x-auto rounded-xl bg-white p-5 shadow-sm"><h2 class="mb-4 font-semibold">Platform invoices</h2><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Invoice</th><th class="p-2">ISP</th><th class="p-2">Period</th><th class="p-2">Total</th><th class="p-2">Balance</th><th class="p-2">Status</th></tr></thead><tbody>
    @forelse($invoices as $invoice)<tr class="border-t"><td class="p-2">{{ $invoice->number }}</td><td class="p-2">{{ $invoice->tenant->name }}</td><td class="p-2">{{ $invoice->period_start->format('Y-m-d') }} – {{ $invoice->period_end->format('Y-m-d') }}</td><td class="p-2">{{ $invoice->currency }} {{ intdiv($invoice->total_minor,100) }}.{{ str_pad((string)($invoice->total_minor%100),2,'0',STR_PAD_LEFT) }}</td><td class="p-2">{{ $invoice->currency }} {{ intdiv($invoice->balance_minor,100) }}.{{ str_pad((string)($invoice->balance_minor%100),2,'0',STR_PAD_LEFT) }}</td><td class="p-2">{{ $invoice->status }}</td></tr>@empty<tr><td colspan="6" class="p-2 text-slate-500">No platform invoices yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
</x-layouts.app>
