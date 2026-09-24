<?php

namespace Database\Factories;

use App\Alarms\AlarmDirection;
use App\Alarms\AlarmType;
use App\Models\AlarmRule;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlarmRule>
 */
class AlarmRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Left out inside a tenant, so BelongsToTenant fills the current one
            ...(tenancy()->initialized ? [] : ['company_id' => Company::factory()]),
            'type' => AlarmType::Threshold,
            'direction' => AlarmDirection::High,
            'threshold' => 8.0,
            'trigger_after' => 600,
            'clear_after' => 0,
        ];
    }
}
