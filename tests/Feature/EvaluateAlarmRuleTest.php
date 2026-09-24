<?php

use App\Actions\Alarms\EvaluateAlarmRule;
use App\Alarms\AlarmDirection;
use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use Carbon\CarbonImmutable;

// Prefixed because Pest loads every test file into the same process, and a
// redeclared global function is a fatal error.
function alarmRule(array $attributes = []): AlarmRule
{
    return AlarmRule::factory()->make([
        // Given, so that nothing in this file touches the database.
        'company_id' => 1,
        'direction' => AlarmDirection::High,
        'threshold' => 8.0,
        'trigger_after' => 600,
        'clear_after' => 0,
        'max_reading_age' => 3600,
        ...$attributes,
    ]);
}

function alarmMonitor(array $attributes = []): AlarmMonitor
{
    return new AlarmMonitor([
        'status' => AlarmStatus::Ok,
        'status_since' => CarbonImmutable::parse('2026-09-23T10:00:00Z'),
        ...$attributes,
    ]);
}

function evaluateReading(AlarmRule $rule, AlarmMonitor $monitor, string $time, float $value, string $now = '2026-09-23T10:00:00Z'): bool
{
    return app(EvaluateAlarmRule::class)->handle(
        $rule,
        $monitor,
        CarbonImmutable::parse($time),
        $value,
        CarbonImmutable::parse($now),
    );
}

it('stamps the crossing without alarming yet', function () {
    $monitor = alarmMonitor();

    $changed = evaluateReading(alarmRule(), $monitor, '2026-09-23T10:00:00Z', 9.0);

    expect($changed)->toBeFalse()
        ->and($monitor->status)->toBe(AlarmStatus::Ok)
        ->and($monitor->pending_since->toIso8601String())->toBe('2026-09-23T10:00:00+00:00')
        ->and($monitor->last_value)->toBe(9.0);
});

it('alarms when the reading clock passes the duration', function () {
    $monitor = alarmMonitor(['pending_since' => CarbonImmutable::parse('2026-09-23T10:00:00Z')]);

    $changed = evaluateReading(alarmRule(), $monitor, '2026-09-23T10:10:00Z', 9.0, '2026-09-23T10:10:00Z');

    expect($changed)->toBeTrue()
        ->and($monitor->status)->toBe(AlarmStatus::Alarm)
        ->and($monitor->pending_since)->toBeNull()
        ->and($monitor->status_since->toIso8601String())->toBe('2026-09-23T10:10:00+00:00');
});

it('does not alarm one second early', function () {
    $monitor = alarmMonitor(['pending_since' => CarbonImmutable::parse('2026-09-23T10:00:00Z')]);

    $changed = evaluateReading(alarmRule(), $monitor, '2026-09-23T10:09:59Z', 9.0, '2026-09-23T10:09:59Z');

    expect($changed)->toBeFalse()
        ->and($monitor->status)->toBe(AlarmStatus::Ok);
});

it('clears the stamp when the value comes back', function () {
    $monitor = alarmMonitor(['pending_since' => CarbonImmutable::parse('2026-09-23T10:00:00Z')]);

    $changed = evaluateReading(alarmRule(), $monitor, '2026-09-23T10:05:00Z', 7.0, '2026-09-23T10:05:00Z');

    expect($changed)->toBeFalse()
        ->and($monitor->pending_since)->toBeNull()
        ->and($monitor->status)->toBe(AlarmStatus::Ok);
});

it('treats the threshold itself as inside a high rule', function () {
    $monitor = alarmMonitor();

    evaluateReading(alarmRule(), $monitor, '2026-09-23T10:00:00Z', 8.0);

    expect($monitor->pending_since)->toBeNull();
});

it('treats the threshold itself as inside a low rule', function () {
    $monitor = alarmMonitor();

    evaluateReading(alarmRule(['direction' => AlarmDirection::Low, 'threshold' => -20.0]), $monitor, '2026-09-23T10:00:00Z', -20.0);

    expect($monitor->pending_since)->toBeNull();
});

it('alarms on the crossing reading when the duration is zero', function () {
    $monitor = alarmMonitor();

    $changed = evaluateReading(alarmRule(['trigger_after' => 0]), $monitor, '2026-09-23T10:00:00Z', 9.0);

    expect($changed)->toBeTrue()
        ->and($monitor->status)->toBe(AlarmStatus::Alarm)
        ->and($monitor->pending_since)->toBeNull();
});

it('recovers on the first reading back inside by default', function () {
    $monitor = alarmMonitor(['status' => AlarmStatus::Alarm]);

    $changed = evaluateReading(alarmRule(), $monitor, '2026-09-23T10:00:00Z', 7.0);

    expect($changed)->toBeTrue()
        ->and($monitor->status)->toBe(AlarmStatus::Ok);
});

it('holds the alarm until the recovery duration passes', function () {
    $monitor = alarmMonitor(['status' => AlarmStatus::Alarm]);
    $rule = alarmRule(['clear_after' => 300]);

    expect(evaluateReading($rule, $monitor, '2026-09-23T10:00:00Z', 7.0))->toBeFalse()
        ->and($monitor->status)->toBe(AlarmStatus::Alarm)
        ->and($monitor->pending_since->toIso8601String())->toBe('2026-09-23T10:00:00+00:00');

    expect(evaluateReading($rule, $monitor, '2026-09-23T10:05:00Z', 7.0, '2026-09-23T10:05:00Z'))->toBeTrue()
        ->and($monitor->status)->toBe(AlarmStatus::Ok);
});

it('starts a waiting monitor at ok, never straight at alarm', function () {
    $monitor = alarmMonitor(['status' => AlarmStatus::Waiting]);

    $changed = evaluateReading(alarmRule(), $monitor, '2026-09-23T10:00:00Z', 99.0);

    expect($changed)->toBeFalse()
        ->and($monitor->status)->toBe(AlarmStatus::Ok)
        ->and($monitor->pending_since->toIso8601String())->toBe('2026-09-23T10:00:00+00:00');
});

it('ignores a reading not newer than the cursor', function () {
    $monitor = alarmMonitor(['evaluated_through' => CarbonImmutable::parse('2026-09-23T10:00:00Z')]);

    $changed = evaluateReading(alarmRule(), $monitor, '2026-09-23T10:00:00Z', 9.0);

    expect($changed)->toBeFalse()
        ->and($monitor->pending_since)->toBeNull()
        ->and($monitor->last_value)->toBeNull();
});

it('ignores a reading older than the tolerance', function () {
    $monitor = alarmMonitor();

    $changed = evaluateReading(alarmRule(['max_reading_age' => 3600]), $monitor, '2026-09-23T08:00:00Z', 9.0, '2026-09-23T10:00:00Z');

    expect($changed)->toBeFalse()
        ->and($monitor->pending_since)->toBeNull()
        ->and($monitor->evaluated_through)->toBeNull();
});

it('holds the cursor at our own clock for a reading from the future', function () {
    $monitor = alarmMonitor();

    evaluateReading(alarmRule(), $monitor, '2026-09-23T10:03:00Z', 9.0, '2026-09-23T10:00:00Z');

    expect($monitor->evaluated_through->toIso8601String())->toBe('2026-09-23T10:00:00+00:00')
        ->and($monitor->pending_since->toIso8601String())->toBe('2026-09-23T10:03:00+00:00');
});

it('schedules the check for what is left of the duration', function () {
    $monitor = alarmMonitor(['pending_since' => CarbonImmutable::parse('2026-09-23T10:00:00Z')]);

    evaluateReading(alarmRule(), $monitor, '2026-09-23T10:04:00Z', 9.0, '2026-09-23T10:04:30Z');

    expect($monitor->next_check_at->toIso8601String())->toBe('2026-09-23T10:10:30+00:00');
});

it('schedules nothing while the value is on the side of the status', function () {
    $monitor = alarmMonitor(['next_check_at' => CarbonImmutable::parse('2026-09-23T10:10:00Z')]);

    evaluateReading(alarmRule(), $monitor, '2026-09-23T10:00:00Z', 7.0);

    expect($monitor->next_check_at)->toBeNull();
});

it('does not push the check past the duration when readings arrive out of order', function () {
    $monitor = alarmMonitor();

    // Stamped from the future, then a reading older than the stamp: the time
    // between the two cannot count as less than nothing.
    evaluateReading(alarmRule(), $monitor, '2026-09-23T10:03:00Z', 9.0, '2026-09-23T10:00:00Z');
    evaluateReading(alarmRule(), $monitor, '2026-09-23T10:01:00Z', 9.0, '2026-09-23T10:01:00Z');

    expect($monitor->next_check_at->toIso8601String())->toBe('2026-09-23T10:11:00+00:00');
});
