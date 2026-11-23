<x-layouts.app>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Routers</h1>
            <p class="mt-1 text-sm text-slate-500">Administrative status controls RADIUS access. Telemetry reports are shown separately.</p>
        </div>
        <a class="rounded bg-indigo-700 px-4 py-2 text-white" href="{{ route('admin.routers.create') }}">Register router</a>
    </div>

    <div class="mt-6 overflow-x-auto rounded bg-white shadow">
        <table class="w-full min-w-[850px] text-left text-sm">
            <thead><tr class="border-b"><th class="p-3">Name</th><th>Mode</th><th>Endpoint / agent</th><th>Access</th><th>Telemetry</th><th>Credentials</th><th></th></tr></thead>
            <tbody>
                @forelse($routers as $router)
                    @php
                        $telemetryFresh = $router->telemetry_updated_at?->greaterThanOrEqualTo(now()->subMinutes(5)) ?? false;
                        $telemetryLabel = $router->telemetry_updated_at ? ($telemetryFresh ? 'Reporting' : 'Stale') : 'No check-in';
                    @endphp
                    <tr class="border-b">
                        <td class="p-3">{{ $router->name }}</td>
                        <td>{{ strtoupper($router->connection_mode) }}</td>
                        <td>{{ $router->endpoint ?? $router->agent_id }}</td>
                        <td><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $router->status === 'active', 'bg-slate-100 text-slate-600' => $router->status !== 'active'])>{{ $router->status === 'active' ? 'Enabled' : 'Disabled' }}</span></td>
                        <td><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $telemetryFresh, 'bg-amber-50 text-amber-800' => ! $telemetryFresh])>{{ $telemetryLabel }}</span>@if($router->telemetry_updated_at)<span class="mt-1 block text-xs text-slate-500">{{ $router->telemetry_updated_at->diffForHumans() }}</span>@endif</td>
                        <td>{{ $router->connection_mode === 'vpn' ? 'Saved securely' : 'Not applicable' }}</td>
                        <td><a class="underline" href="{{ route('admin.routers.edit', $router) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td class="p-4" colspan="7">No routers registered.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $routers->links() }}
</x-layouts.app>
