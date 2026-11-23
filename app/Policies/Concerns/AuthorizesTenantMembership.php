<?php

namespace App\Policies\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait AuthorizesTenantMembership
{
    protected function roleForCurrentTenant(User $user): ?string
    {
        try {
            $tenantId = app(TenantContext::class)->id();
        } catch (LogicException) {
            return null;
        }

        return $user->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->value('role');
    }

    protected function canReadTenantRecord(User $user, Model $record): bool
    {
        $tenantId = $this->roleForCurrentTenant($user);

        return $tenantId !== null && (string) $record->tenant_id === app(TenantContext::class)->id();
    }

    protected function canManageCurrentTenant(User $user): bool
    {
        return in_array($this->roleForCurrentTenant($user), ['owner', 'admin'], true);
    }
}
