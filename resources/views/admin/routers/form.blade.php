@php($values = $router ?? null)
@if($values?->connection_mode === 'agent')
    <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">This router was saved in the unfinished agent mode. To move it to the supported integration, enter its private VPN address and RADIUS shared secret below.</p>
@endif
<label class="block">Router name<input class="mt-1 w-full rounded border p-2" name="name" required value="{{ old('name', $values?->name) }}"></label>
<input type="hidden" name="connection_mode" value="vpn">
<div class="space-y-4 rounded bg-slate-50 p-4">
    <h2 class="font-semibold">FreeRADIUS over private VPN</h2>
    <p class="text-sm text-slate-600">PPPoE authentication, accounting, and disconnects use RADIUS. RouterOS API credentials are not needed for this connection mode.</p>
    <label class="block">Private VPN address<input class="mt-1 w-full rounded border p-2" name="endpoint" placeholder="10.0.0.5" value="{{ old('endpoint', $values?->connection_mode === 'vpn' ? $values?->endpoint : null) }}" required><span class="text-xs text-slate-500">Use the private address FreeRADIUS sees as the NAS source address.</span></label>
    <label class="block">RADIUS shared secret<input class="mt-1 w-full rounded border p-2" type="password" name="radius_secret" autocomplete="new-password"><span class="text-xs text-slate-500">Required on first save. Stored encrypted; leave blank on edit to keep the existing secret.</span></label>
</div>
<label class="block">Access status<select class="mt-1 w-full rounded border p-2" name="status"><option value="active" @selected(old('status', $values?->status ?? 'active') === 'active')>Enabled</option><option value="inactive" @selected(old('status', $values?->status) === 'inactive')>Disabled</option></select></label>
