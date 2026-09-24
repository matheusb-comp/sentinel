<?php

namespace Database\Factories;

use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use App\Models\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlarmMonitor>
 */
class AlarmMonitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alarm_rule_id' => AlarmRule::factory(),
            'sensor_id' => Sensor::factory(),
            'status' => AlarmStatus::Waiting,
            'status_since' => now(),
        ];
    }
}
