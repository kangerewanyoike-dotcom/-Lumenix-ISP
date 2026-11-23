<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantMembership;

class PlanPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->roleForCurrentTenant($user) !== null;
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->canReadTenantRecord($user, $plan);
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentTenant($user);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->canReadTenantRecord($user, $plan) && $this->canManageCurrentTenant($user);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->update($user, $plan);
    }
}
