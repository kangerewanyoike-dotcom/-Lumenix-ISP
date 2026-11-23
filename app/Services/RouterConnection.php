<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Router;

interface RouterConnection
{
    public function disconnect(Router $router, Customer $customer): bool;
}
