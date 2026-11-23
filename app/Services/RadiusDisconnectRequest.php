<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Router;

final class RadiusDisconnectRequest
{
    /** @return array<string, string> */
    public function attributes(Router $router, Customer $customer): array
    {
        if (! $router->endpoint || ! $customer->pppoe_username) {
            throw new \InvalidArgumentException('A router endpoint and PPPoE username are required.');
        }

        return ['User-Name' => $customer->pppoe_username, 'NAS-IP-Address' => $router->endpoint];
    }

    public function payload(Router $router, Customer $customer): string
    {
        $attributes = $this->attributes($router, $customer);

        return 'User-Name = "'.$this->quote($attributes['User-Name']).'"'."\n".'NAS-IP-Address = '.$attributes['NAS-IP-Address']."\n";
    }

    private function quote(string $value): string
    {
        return addcslashes($value, '\\"');
    }
}
