<?php

use App\Alarms\AlarmDirection;
use App\Alarms\AlarmType;
use App\Models\AlarmMonitor;
use App\Models\AlarmPeriod;
use Illuminate\Database\QueryException;

it('refuses a second open period for the same monitor', function () {
    $monitor = AlarmMonitor::factory()->create();
    AlarmPeriod::factory()->forMonitor($monitor)->create();

    $second = fn () => AlarmPeriod::factory()->forMonitor($monitor)->create();

    expect($second)->toThrow(QueryException::class);
});

it('accepts a new period once the previous one is closed', function () {
    $monitor = AlarmMonitor::factory()->create();
    AlarmPeriod::factory()->forMonitor($monitor)->closed()->create();

    AlarmPeriod::factory()->forMonitor($monitor)->create();

    expect(AlarmPeriod::where('alarm_monitor_id', $monitor->id)->count())->toBe(2);
});

it('accepts open periods of different monitors', function () {
    $rule = AlarmMonitor::factory()->create()->rule;
    $monitors = AlarmMonitor::factory()->count(2)->create(['alarm_rule_id' => $rule->id]);

    $monitors->each(fn (AlarmMonitor $monitor) => AlarmPeriod::factory()->forMonitor($monitor)->create());

    expect(AlarmPeriod::whereNull('ended_at')->count())->toBe(2);
});

it('stays readable after the rule it came from is deleted', function () {
    $monitor = AlarmMonitor::factory()->create();
    $period = AlarmPeriod::factory()->forMonitor($monitor)->create(['label' => 'Câmara 1']);

    $monitor->rule->delete();

    expect($period->fresh())
        ->alarm_monitor_id->toBeNull()
        ->type->toBe(AlarmType::Threshold)
        ->direction->toBe(AlarmDirection::High)
        ->threshold->toBe(8.0)
        ->trigger_after->toBe(600)
        ->label->toBe('Câmara 1');
});
