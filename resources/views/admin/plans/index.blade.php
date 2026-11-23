<x-layouts.app>
<div class="flex items-center justify-between"><h1 class="text-2xl font-semibold">Plans</h1><a class="rounded bg-indigo-700 px-4 py-2 text-white" href="{{ route('admin.plans.create') }}">Add plan</a></div>
<div class="mt-6 overflow-x-auto rounded bg-white shadow"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Name</th><th>Speed down/up</th><th>Price / period</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($plans as $plan)<tr class="border-b"><td class="p-3">{{ $plan->name }}</td><td>{{ $plan->speed_down_kbps / 1000 }} / {{ $plan->speed_up_kbps / 1000 }} Mbps</td><td>KES {{ number_format($plan->price_minor / 100, 2) }} / {{ $plan->billing_period }}</td><td>{{ $plan->active ? 'Active' : 'Inactive' }}</td><td><a class="underline" href="{{ route('admin.plans.edit', $plan) }}">Edit</a></td></tr>@empty<tr><td class="p-4" colspan="5">No plans yet.</td></tr>@endforelse
</tbody></table></div>{{ $plans->links() }}
</x-layouts.app>
