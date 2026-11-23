<x-layouts.app>
<h1 class="mb-2 text-2xl font-semibold">Messaging and automation</h1>
<p class="mb-6 max-w-3xl text-sm text-slate-600">Automation starts disabled. Set a grace period and reminder schedule, then test SMS delivery in Africa’s Talking sandbox before enabling production messaging.</p>
<section class="mb-6 grid gap-3 sm:grid-cols-3"><article class="rounded-xl border border-teal-100 bg-teal-50/70 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-teal-800">SMS</p><p class="mt-2 text-sm font-semibold">{{ $account?->active ? 'Configured · '.$account->environment : 'Not configured' }}</p></article><article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</p><p class="mt-2 text-sm font-semibold">Provider integration not connected</p></article><article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">WhatsApp</p><p class="mt-2 text-sm font-semibold">Provider integration not connected</p></article></section>
<form method="POST" action="{{ route('admin.automation.settings') }}" class="mb-8 max-w-2xl space-y-4 rounded bg-white p-6">@csrf @method('PUT')
<label class="flex items-center gap-2"><input type="checkbox" name="automation_enabled" value="1" @checked($tenant->automation_enabled)> Enable reminders and automatic access suspension</label>
<label class="block">Grace period after invoice due date (days)<input class="mt-1 w-full rounded border p-2" type="number" min="0" max="30" name="grace_period_days" required value="{{ old('grace_period_days', $tenant->grace_period_days) }}"></label>
<fieldset><legend class="mb-2 font-medium">Reminder days before due date</legend><div class="flex flex-wrap gap-4">@foreach([3,1,0] as $day)<label class="flex items-center gap-2"><input type="checkbox" name="reminder_days[]" value="{{ $day }}" @checked(in_array($day, $tenant->reminder_days ?? [3,1,0]))>{{ $day === 0 ? 'Due date' : $day.' days before' }}</label>@endforeach</div><p class="mt-1 text-xs text-slate-500">Select up to eight day offsets from 0 to 30; 0 sends on the due date.</p></fieldset>
<button class="rounded bg-indigo-700 px-4 py-2 text-white">Save policy</button></form>

<h2 class="mb-3 text-xl font-semibold">Africa’s Talking SMS account</h2>
<form method="POST" action="{{ route('admin.automation.messaging') }}" class="mb-8 max-w-2xl space-y-3 rounded bg-white p-6">@csrf @method('PUT')
<select class="w-full rounded border p-2" name="environment"><option value="sandbox" @selected(($account?->environment ?? 'sandbox') === 'sandbox')>Sandbox</option><option value="production" @selected($account?->environment === 'production')>Production</option></select>
<input class="w-full rounded border p-2" name="username" placeholder="Africa’s Talking username" value="{{ old('username', $account?->username) }}" required>
<input class="w-full rounded border p-2" name="api_key" type="password" placeholder="{{ $account ? 'Leave empty to keep the saved API key' : 'API key' }}" @required(!$account)>
<input class="w-full rounded border p-2" name="sender_id" placeholder="Approved sender ID (optional)" value="{{ old('sender_id', $account?->sender_id) }}">
<label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked($account?->active)> Account active</label>
<button class="rounded bg-indigo-700 px-4 py-2 text-white">Save SMS account</button></form>

<h2 class="mb-3 text-xl font-semibold">Invoice reminder template</h2>
<form method="POST" action="{{ route('admin.automation.template') }}" class="mb-8 max-w-2xl space-y-3 rounded bg-white p-6">@csrf @method('PUT')
<textarea class="w-full rounded border p-2" rows="4" name="body" maxlength="500" required>{{ old('body', $templates->firstWhere('event_key', 'invoice_reminder')?->body ?? $defaultTemplate) }}</textarea>
<p class="text-xs text-slate-500">Allowed variables: &#123;&#123;customer_name&#125;&#125;, &#123;&#123;account_number&#125;&#125;, &#123;&#123;invoice_number&#125;&#125;, &#123;&#123;amount&#125;&#125;, &#123;&#123;due_date&#125;&#125;.</p><button class="rounded bg-indigo-700 px-4 py-2 text-white">Save template</button></form>

<h2 class="mb-3 text-xl font-semibold">Recent SMS deliveries</h2>
<div class="overflow-x-auto rounded bg-white"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Event</th><th>Status</th><th>Attempts</th><th>Provider reference</th><th>Error</th><th>Updated</th></tr></thead><tbody>@forelse($deliveries as $delivery)<tr class="border-b"><td class="p-3">{{ $delivery->event_key }}</td><td>{{ ucfirst($delivery->status) }}</td><td>{{ $delivery->attempts }}</td><td>{{ $delivery->provider_reference ?? '—' }}</td><td>{{ $delivery->error_code ?? '—' }}</td><td>{{ $delivery->updated_at->format('Y-m-d H:i') }}</td></tr>@empty<tr><td class="p-3" colspan="6">No deliveries yet.</td></tr>@endforelse</tbody></table></div>
</x-layouts.app>
