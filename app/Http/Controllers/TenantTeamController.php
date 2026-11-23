<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class TenantTeamController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $this->authorizeManager($request);

        return view('admin.team.index', [
            'memberships' => TenantMembership::query()->with('user')->where('tenant_id', $context->id())->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('role')->orderBy('id')->get(),
            'canManageAdmins' => $request->attributes->get('tenant_membership')->role === 'owner',
        ]);
    }

    public function store(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', 'in:owner,admin,support,agent'],
        ]);
        abort_if($actor->role !== 'owner' && in_array($data['role'], ['owner', 'admin'], true), 403);

        $email = Str::lower(trim($data['email']));
        $result = DB::transaction(function () use ($context, $data, $email): array {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            $isNewUser = ! $user;
            $user ??= User::create([
                'name' => $data['name'],
                'email' => $email,
                'password' => Str::random(64),
            ]);

            $membership = TenantMembership::query()->where('tenant_id', $context->id())->where('user_id', $user->id)->first();
            abort_if($membership?->status === 'active', 422, 'This person is already an active member of this workspace.');

            if ($membership) {
                $membership->update(['role' => $data['role'], 'status' => 'active']);
            } else {
                $membership = TenantMembership::create([
                    'tenant_id' => $context->id(),
                    'user_id' => $user->id,
                    'role' => $data['role'],
                    'status' => 'active',
                ]);
            }

            return [$user, $membership, $isNewUser];
        });

        [$user, $membership, $isNewUser] = $result;
        $audit->record('team.member_added', (int) $context->id(), TenantMembership::class, (string) $membership->id, ['role' => $membership->role]);

        if ($isNewUser) {
            try {
                $resetStatus = Password::sendResetLink(['email' => $email]);
                if ($resetStatus !== Password::RESET_LINK_SENT) {
                    return back()->with('warning', 'The member was added, but the password setup email could not be sent. Use Send password setup link to try again.');
                }
            } catch (Throwable) {
                return back()->with('warning', 'The member was added, but email delivery failed. Configure mail and use Send password setup link to try again.');
            }
        }

        return back()->with('status', $isNewUser
            ? 'Team member added. A password setup link has been sent to '.$user->email.'.'
            : 'Existing Lumenix account added to this workspace. They can sign in with their current password.');
    }

    public function update(Request $request, string $membership, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        $target = $this->membership($membership, $context);
        $data = $request->validate(['role' => ['required', 'in:owner,admin,support,agent']]);
        $this->authorizeTarget($actor, $target, $data['role']);

        if ($target->role === 'owner' && $data['role'] !== 'owner') {
            $this->preserveLastOwner($target, $context);
        }

        $target->update(['role' => $data['role']]);
        $audit->record('team.role_updated', (int) $context->id(), TenantMembership::class, (string) $target->id, ['role' => $target->role]);

        return back()->with('status', 'Team member role updated.');
    }

    public function status(Request $request, string $membership, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        $target = $this->membership($membership, $context);
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        abort_if((int) $target->user_id === (int) $request->user()->id && $data['status'] === 'suspended', 422, 'You cannot suspend your own account.');
        $this->authorizeTarget($actor, $target);

        if ($target->role === 'owner' && $data['status'] !== 'active') {
            $this->preserveLastOwner($target, $context);
        }

        $target->update(['status' => $data['status']]);
        $audit->record('team.member_'.$data['status'], (int) $context->id(), TenantMembership::class, (string) $target->id);

        return back()->with('status', $data['status'] === 'active' ? 'Team member access restored.' : 'Team member access suspended.');
    }

    public function sendPasswordReset(Request $request, string $membership, TenantContext $context): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        $target = $this->membership($membership, $context);
        $this->authorizeTarget($actor, $target);

        try {
            $status = Password::sendResetLink(['email' => $target->user->email]);
        } catch (Throwable) {
            return back()->with('warning', 'Email delivery failed. Check the mail configuration and try again.');
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Password setup/reset link sent to '.$target->user->email.'.')
            : back()->with('warning', __($status));
    }

    private function authorizeManager(Request $request): TenantMembership
    {
        $membership = $request->attributes->get('tenant_membership');
        abort_unless($membership instanceof TenantMembership && in_array($membership->role, ['owner', 'admin'], true), 403);

        return $membership;
    }

    private function membership(string $id, TenantContext $context): TenantMembership
    {
        return TenantMembership::query()->with('user')->where('tenant_id', $context->id())->findOrFail($id);
    }

    private function authorizeTarget(TenantMembership $actor, TenantMembership $target, ?string $newRole = null): void
    {
        abort_if((int) $actor->user_id === (int) $target->user_id, 422, 'Manage another team member to change access or roles.');
        abort_if($actor->role !== 'owner' && (in_array($target->role, ['owner', 'admin'], true) || in_array($newRole, ['owner', 'admin'], true)), 403);
    }

    private function preserveLastOwner(TenantMembership $target, TenantContext $context): void
    {
        $activeOwners = TenantMembership::query()->where('tenant_id', $context->id())->where('role', 'owner')->where('status', 'active')->count();
        abort_if($activeOwners <= 1, 422, 'A workspace must keep at least one active owner.');
    }
}
