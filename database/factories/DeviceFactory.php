<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Left out inside a tenant, so BelongsToTenant fills the current one
            ...(tenancy()->initialized ? [] : ['company_id' => Company::factory()]),
            'key' => fake()->word().'-'.fake()->unique()->numberBetween(1, 999999),
            'label' => fake()->words(3, true),
        ];
    }
}
