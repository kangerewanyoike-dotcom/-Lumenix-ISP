<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price_minor' => fake()->numberBetween(50000, 500000),
            'currency' => 'KES',
            'speed_down_kbps' => fake()->randomElement([5_000, 10_000, 20_000, 50_000]),
            'speed_up_kbps' => fake()->randomElement([2_500, 5_000, 10_000, 25_000]),
            'billing_period' => 'monthly',
            'data_cap_bytes' => null,
            'active' => true,
        ];
    }
}
