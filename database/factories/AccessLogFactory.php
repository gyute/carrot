<?php

namespace Database\Factories;

use App\Models\AccessLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessLog>
 */
class AccessLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->userName(),
            'ip_address' => fake()->ipv4(),
            'path' => fake()->randomElement([
                '/', '/tools', '/tools/exports', '/settings/profile',
                '/login', '/settings/security', '/tools/exports/jobs',
            ]),
            'status_code' => fake()->randomElement([200, 200, 200, 302, 404, 500]),
            'duration_ms' => fake()->numberBetween(12, 1800),
            'accessed_at' => fake()->dateTimeBetween('-14 days'),
        ];
    }
}
