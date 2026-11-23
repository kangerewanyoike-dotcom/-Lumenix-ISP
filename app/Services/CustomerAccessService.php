<?php

namespace App\Services;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Router;

final class CustomerAccessService
{
    public function __construct(private readonly RouterConnection $connection, private readonly TenantContext $context) {}

    public function suspend(Customer $customer, Router $router): void
    {
        $this->transition($customer, $router, 'suspended');
    }

    public function reactivate(Customer $customer, Router $router): void
    {
        $this->transition($customer, $router, 'active');
    }

    private function transition(Customer $customer, Router $router, string $status): void
    {
        if ((string) $customer->tenant_id !== $this->context->id() || (string) $router->tenant_id !== $this->context->id()) {
            throw new \LogicException('Customer and router must belong to the active tenant.');
        }
        if ($router->connection_mode !== 'vpn' || $router->status !== 'active') {
            throw new \LogicException('Disconnect requests currently require an active VPN router.');
        }

        $customer->update(['status' => $status]);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($this->connection->disconnect($router, $customer)) {
                return;
            }
        }

        throw new \RuntimeException('Router did not acknowledge the Disconnect-Request after 3 attempts. RADIUS authorization state was updated.');
    }
}
