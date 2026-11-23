<?php

namespace Database\Factories;

use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Router> */
class RouterFactory extends Factory
{
    protected $model = Router::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' router',
            'connection_mode' => 'vpn',
            'endpoint' => '10.0.0.'.fake()->numberBetween(2, 254),
            'agent_id' => null,
            'api_username' => 'lumenix-'.Str::lower(Str::random(8)),
            'api_password' => Str::random(32),
            'api_port' => 8729,
            'use_tls' => true,
            'status' => 'active',
            'last_seen_at' => null,
        ];
    }
}
