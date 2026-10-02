<?php

namespace Database\Factories;

use App\Alarms\AlarmDirection;
use App\Alarms\AlarmEndReason;
use App\Alarms\AlarmType;
use App\Models\AlarmMonitor;
use App\Models\AlarmPeriod;
use App\Models\Company;
use App\Models\Sensor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlarmPeriod>
 */
class AlarmPeriodFactory extends Factory
{
    /**
     * An open period, breached twenty minutes ago and in alarm for the last ten.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Left out inside a tenant, so BelongsToTenant fills the current one
            ...(tenancy()->initialized ? [] : ['company_id' => Company::factory()]),
            'sensor_id' => Sensor::factory(),
            'breached_at' => now()->subMinutes(20),
            'started_at' => now()->subMinutes(10),
            'value' => 9.5,
            'type' => AlarmType::Threshold,
            'direction' => AlarmDirection::High,
            'threshold' => 8.0,
            'trigger_after' => 600,
            'clear_after' => 0,
        ];
    }

    /**
     * Hangs the period on a monitor, carrying the condition of its rule.
     */
    public function forMonitor(AlarmMonitor $monitor): self
    {
        $rule = $monitor->rule;

        return $this->state([
            'company_id' => $rule->company_id,
            'sensor_id' => $monitor->sensor_id,
            'alarm_monitor_id' => $monitor->id,
            'type' => $rule->type,
            'label' => $rule->label,
            'direction' => $rule->direction,
            'threshold' => $rule->threshold,
            'trigger_after' => $rule->trigger_after,
            'clear_after' => $rule->clear_after,
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'ended_at' => CarbonImmutable::parse($attributes['started_at'])->addMinutes(5),
            'ended_reason' => AlarmEndReason::Transition,
        ]);
    }
}
