<?php

use App\Models\AlarmMonitor;
use App\Models\AlarmRule;

it('falls back to the configured reading age', function () {
    config(['alarms.max_reading_age' => 900]);

    expect(AlarmRule::factory()->make(['max_reading_age' => null])->maxReadingAge())->toBe(900)
        ->and(AlarmRule::factory()->make(['max_reading_age' => 60])->maxReadingAge())->toBe(60);
});

it('takes its monitors with it', function () {
    $monitor = AlarmMonitor::factory()->create();

    $monitor->rule->delete();

    expect(AlarmMonitor::find($monitor->id))->toBeNull();
});
