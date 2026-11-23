<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantMembership;

class InvoicePolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->roleForCurrentTenant($user) !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->canReadTenantRecord($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentTenant($user);
    }
}
