<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PrivateRouterEndpoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
            $fail('Enter a valid IP address for the VPN endpoint.');

            return;
        }

        $packed = inet_pton($value);
        $private = false;

        if ($packed !== false && strlen($packed) === 4) {
            $first = ord($packed[0]);
            $second = ord($packed[1]);
            $private = $first === 10
                || ($first === 172 && $second >= 16 && $second <= 31)
                || ($first === 192 && $second === 168)
                || ($first === 100 && $second >= 64 && $second <= 127);
        } elseif ($packed !== false && strlen($packed) === 16) {
            $private = (ord($packed[0]) & 0xFE) === 0xFC;
        }

        if (! $private) {
            $fail('Use a private or CGNAT address reachable through the tenant VPN.');
        }
    }
}
