<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use LogicException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function id(): string
    {
        return $this->tenant?->getKey() ?? throw new LogicException('Tenant context is required.');
    }

    public function current(): Tenant
    {
        return $this->tenant ?? throw new LogicException('Tenant context is required.');
    }
}
