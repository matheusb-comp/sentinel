<?php

use App\Actions\Alarms\EvaluateAlarms;
use App\Alarms\AlarmStatus;
use App\Alarms\AlarmType;
use App\Events\ReadingsStored;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use App\Models\Sensor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// The readings below carry fixed instants, and how old a reading is decides
// whether it moves a monitor at all.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-23 10:15:00', 'UTC'));
});

/**
 * A sensor watched against a rule of its own company.
 */
function watchSensor(Sensor $sensor, array $rule = []): AlarmMonitor
{
    return AlarmMonitor::factory()->create([
        'alarm_rule_id' => AlarmRule::factory()->create([
            'company_id' => $sensor->device->company_id,
            ...$rule,
        ])->id,
        'sensor_id' => $sensor->id,
    ]);
}

function alarmReading(Sensor $sensor, string $time, float $value): array
{
    return ['sensor_id' => $sensor->id, 'time' => $time, 'value' => $value];
}

it('walks the readings of a sensor in order of their own instant', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['trigger_after' => 600]);

    // Out of order on purpose: the batch covers eleven minutes above the
    // threshold, so the duration is only met when they are walked in order.
    app(EvaluateAlarms::class)->handle([
        alarmReading($sensor, '2026-09-23 10:11:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:05:00+00', 9.0),
    ]);

    expect($watch->refresh()->status)->toBe(AlarmStatus::Alarm);
});

it('does not count a repeated reading twice', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['trigger_after' => 600]);

    app(EvaluateAlarms::class)->handle([
        alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0),
    ]);

    expect($watch->refresh()->status)->toBe(AlarmStatus::Ok)
        ->and($watch->pending_since->toIso8601String())->toBe('2026-09-23T10:00:00+00:00');
});

it('watches every sensor one rule is applied to', function () {
    $first = Sensor::factory()->create();
    $second = Sensor::factory()->create();
    $rule = AlarmRule::factory()->create(['trigger_after' => 0]);
    $watches = AlarmMonitor::factory()
        ->count(2)
        ->sequence(['sensor_id' => $first->id], ['sensor_id' => $second->id])
        ->create(['alarm_rule_id' => $rule->id]);

    app(EvaluateAlarms::class)->handle([
        alarmReading($first, '2026-09-23 10:00:00+00', 9.0),
        alarmReading($second, '2026-09-23 10:00:00+00', 1.0),
    ]);

    expect($watches[0]->refresh()->status)->toBe(AlarmStatus::Alarm)
        ->and($watches[1]->refresh()->status)->toBe(AlarmStatus::Ok);
});

it('lets two rules on the same sensor disagree about how old a reading may be', function () {
    $sensor = Sensor::factory()->create();
    $strict = watchSensor($sensor, ['trigger_after' => 0, 'max_reading_age' => 60]);
    $lax = watchSensor($sensor, ['trigger_after' => 0, 'max_reading_age' => 86400]);

    app(EvaluateAlarms::class)->handle([
        alarmReading($sensor, now()->subHour()->format('Y-m-d H:i:sP'), 9.0),
    ]);

    expect($strict->refresh()->status)->toBe(AlarmStatus::Waiting)
        ->and($lax->refresh()->status)->toBe(AlarmStatus::Alarm);
});

it('leaves an inactive rule alone', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['trigger_after' => 0, 'active' => false]);

    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0)]);

    expect($watch->refresh()->status)->toBe(AlarmStatus::Waiting);
});

it('leaves a rule that does not watch a value alone', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, [
        'type' => AlarmType::NoData,
        'direction' => null,
        'threshold' => null,
        'trigger_after' => 0,
    ]);

    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0)]);

    expect($watch->refresh()->status)->toBe(AlarmStatus::Waiting);
});

it('does not write a monitor no reading moved', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['max_reading_age' => 60]);
    $before = $watch->updated_at;

    app(EvaluateAlarms::class)->handle([
        alarmReading($sensor, now()->subHour()->format('Y-m-d H:i:sP'), 9.0),
    ]);

    expect($watch->refresh()->updated_at->equalTo($before))->toBeTrue();
});

it('costs the same queries whatever the size of the batch', function () {
    $sensor = Sensor::factory()->create();
    watchSensor($sensor);

    $readings = array_map(
        fn (int $minute): array => alarmReading($sensor, "2026-09-23 10:0{$minute}:00+00", 7.0),
        range(0, 9),
    );

    DB::enableQueryLog();

    app(EvaluateAlarms::class)->handle($readings);

    // The monitors, their rules, and the one monitor that moved.
    expect(DB::getQueryLog())->toHaveCount(3);
});

it('evaluates the alarms of a batch the ingestion stored', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['trigger_after' => 0]);

    ReadingsStored::dispatch([alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0)]);

    expect($watch->refresh()->status)->toBe(AlarmStatus::Alarm);
});
