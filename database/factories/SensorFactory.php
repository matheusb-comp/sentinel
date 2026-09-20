<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sensor>
 */
class SensorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'key' => fake()->word().'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->sentence(3),
        ];
    }
}
