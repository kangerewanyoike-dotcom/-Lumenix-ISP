<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Router;

final class FakeRouterConnection implements RouterConnection
{
    /** @var list<array{router_id: int, customer_id: int, result: bool}> */
    public array $calls = [];

    /** @param list<bool> $results */
    public function __construct(private array $results = [true]) {}

    public function disconnect(Router $router, Customer $customer): bool
    {
        $result = array_shift($this->results) ?? false;
        $this->calls[] = ['router_id' => (int) $router->id, 'customer_id' => (int) $customer->id, 'result' => $result];

        return $result;
    }
}
