<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $token = Str::lower(Str::random(10));

        return [
            'plan_id' => null,
            'account_number' => 'ACC-'.$token,
            'name' => fake()->name(),
            'phone' => '+2547'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'pppoe_username' => 'pppoe-'.$token,
            'pppoe_password' => Str::random(24),
            'status' => 'active',
            'billing_day' => 1,
        ];
    }
}
