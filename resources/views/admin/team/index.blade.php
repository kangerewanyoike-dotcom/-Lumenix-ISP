<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div><p class="mb-2 text-xs font-bold uppercase tracking-[.14em] text-indigo-700">Workspace settings</p><h2 class="text-3xl font-semibold">Team access</h2><p class="mt-2 max-w-2xl text-sm text-slate-600">Add colleagues to this ISP workspace and give each person the access they need.</p></div>
        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-800">{{ $memberships->where('status', 'active')->count() }} active members</span>
    </div>

    <section class="mb-7 rounded-xl bg-white p-5 sm:p-6">
        <h3 class="text-lg font-semibold">Add a team member</h3>
        <p class="mb-5 mt-1 text-sm text-slate-600">Existing Lumenix accounts keep their current password. New members receive a one-time password setup link.</p>
        <form method="POST" action="{{ route('admin.team.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <label class="block text-sm font-medium">Full name<input class="mt-1.5 w-full rounded border p-3" name="name" value="{{ old('name') }}" maxlength="160" autocomplete="name" required></label>
            <label class="block text-sm font-medium">Work email<input class="mt-1.5 w-full rounded border p-3" type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required></label>
            <label class="block text-sm font-medium">Workspace role<select class="mt-1.5 w-full rounded border p-3" name="role" required>@if($canManageAdmins)<option value="admin">Admin</option><option value="owner">Owner</option>@endif<option value="support">Support</option><option value="agent">Agent</option></select></label>
            <div class="flex items-end"><button class="w-full rounded-xl bg-indigo-700 px-4 py-3 text-sm font-semibold text-white">Add member</button></div>
        </form>
        @unless($canManageAdmins)<p class="mt-3 text-xs text-slate-500">Only workspace owners can assign owner or admin access.</p>@endunless
    </section>

    <section class="overflow-hidden rounded-xl bg-white">
        <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold">Workspace members</h3><p class="mt-1 text-xs text-slate-500">Suspended members cannot access this workspace. Roles and permissions are scoped to this ISP.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm">
            <thead><tr class="border-b border-slate-100"><th class="px-5 py-3">Member</th><th>Role</th><th>Access</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
            <tbody>
                @forelse($memberships as $membership)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-4"><strong class="block text-slate-900">{{ $membership->user->name }}</strong><span class="text-xs text-slate-500">{{ $membership->user->email }}</span>@if((int) $membership->user_id === (int) auth()->id())<span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">You</span>@endif</td>
                        <td>
                            @if($membership->user_id !== auth()->id() && ($canManageAdmins || ! in_array($membership->role, ['owner', 'admin'], true)))
                                <form method="POST" action="{{ route('admin.team.update', $membership->id) }}" class="flex items-center gap-2">@csrf @method('PATCH')<select class="rounded border p-2 text-xs" name="role" aria-label="Role for {{ $membership->user->name }}">@if($canManageAdmins)<option value="owner" @selected($membership->role === 'owner')>Owner</option><option value="admin" @selected($membership->role === 'admin')>Admin</option>@endif<option value="support" @selected($membership->role === 'support')>Support</option><option value="agent" @selected($membership->role === 'agent')>Agent</option></select><button class="text-xs font-semibold text-indigo-700 underline">Save</button></form>
                            @else<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ ucfirst($membership->role) }}</span>@endif
                        </td>
                        <td><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $membership->status === 'active', 'bg-amber-50 text-amber-800' => $membership->status !== 'active'])>{{ ucfirst($membership->status) }}</span></td>
                        <td class="px-5 py-4"><div class="flex justify-end gap-4">
                            @if($membership->user_id !== auth()->id() && ($canManageAdmins || ! in_array($membership->role, ['owner', 'admin'], true)))
                                <form method="POST" action="{{ route('admin.team.password-reset', $membership->id) }}">@csrf<button class="text-xs font-medium text-indigo-700 underline">Send password setup/reset link</button></form>
                                <form method="POST" action="{{ route('admin.team.status', $membership->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $membership->status === 'active' ? 'suspended' : 'active' }}"><button class="text-xs font-medium {{ $membership->status === 'active' ? 'text-red-700' : 'text-emerald-700' }} underline">{{ $membership->status === 'active' ? 'Suspend' : 'Restore' }}</button></form>
                            @else<span class="text-xs text-slate-400">—</span>@endif
                        </div></td>
                    </tr>
                @empty<tr><td class="px-5 py-8 text-center text-sm text-slate-500" colspan="4">No workspace members found.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
</x-layouts.app>
