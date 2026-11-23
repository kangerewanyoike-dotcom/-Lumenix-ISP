<?php

namespace App\Policies;

use App\Models\Router;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantMembership;

class RouterPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->roleForCurrentTenant($user) !== null;
    }

    public function view(User $user, Router $router): bool
    {
        return $this->canReadTenantRecord($user, $router);
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentTenant($user);
    }

    public function update(User $user, Router $router): bool
    {
        return $this->canReadTenantRecord($user, $router) && $this->canManageCurrentTenant($user);
    }

    public function delete(User $user, Router $router): bool
    {
        return $this->update($user, $router);
    }
}
