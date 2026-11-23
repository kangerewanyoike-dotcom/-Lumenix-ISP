<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantMembership;

class CustomerPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->roleForCurrentTenant($user) !== null;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->canReadTenantRecord($user, $customer);
    }

    public function create(User $user): bool
    {
        return $this->roleForCurrentTenant($user) !== null;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->canReadTenantRecord($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->canReadTenantRecord($user, $customer) && $this->canManageCurrentTenant($user);
    }
}
