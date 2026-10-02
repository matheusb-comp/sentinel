<?php

use App\Actions\Alarms\EvaluateAlarms;
use App\Alarms\AlarmDirection;
use App\Alarms\AlarmEndReason;
use App\Alarms\AlarmStatus;
use App\Alarms\AlarmType;
use App\Events\ReadingsStored;
use App\Models\AlarmMonitor;
use App\Models\AlarmPeriod;
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

it('opens a period when the monitor enters alarm', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['trigger_after' => 600]);

    app(EvaluateAlarms::class)->handle([
        alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:10:00+00', 9.5),
    ]);

    expect(AlarmPeriod::sole())
        ->alarm_monitor_id->toBe($watch->id)
        ->sensor_id->toBe($sensor->id)
        ->company_id->toBe($sensor->device->company_id)
        ->breached_at->toIso8601String()->toBe('2026-09-23T10:00:00+00:00')
        ->started_at->toIso8601String()->toBe('2026-09-23T10:10:00+00:00')
        ->ended_at->toBeNull()
        ->value->toBe(9.5);
});

it('closes the period when the value comes back', function () {
    $sensor = Sensor::factory()->create();
    watchSensor($sensor, ['trigger_after' => 600]);

    app(EvaluateAlarms::class)->handle([
        alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:10:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:12:00+00', 7.0),
    ]);

    expect(AlarmPeriod::sole())
        ->ended_at->toIso8601String()->toBe('2026-09-23T10:12:00+00:00')
        ->ended_reason->toBe(AlarmEndReason::Transition);
});

it('records one period per excursion of the same batch', function () {
    $sensor = Sensor::factory()->create();
    watchSensor($sensor, ['trigger_after' => 0]);

    app(EvaluateAlarms::class)->handle([
        alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:01:00+00', 7.0),
        alarmReading($sensor, '2026-09-23 10:02:00+00', 9.0),
        alarmReading($sensor, '2026-09-23 10:03:00+00', 7.0),
    ]);

    expect(AlarmPeriod::orderBy('started_at')->pluck('ended_at')->map->toIso8601String()->all())
        ->toBe(['2026-09-23T10:01:00+00:00', '2026-09-23T10:03:00+00:00']);
});

it('dates the breach at the confirming reading when the duration is zero', function () {
    $sensor = Sensor::factory()->create();
    watchSensor($sensor, ['trigger_after' => 0]);

    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0)]);

    expect(AlarmPeriod::sole())
        ->breached_at->toIso8601String()->toBe('2026-09-23T10:00:00+00:00')
        ->started_at->toIso8601String()->toBe('2026-09-23T10:00:00+00:00');
});

it('freezes the condition of the rule that judged', function () {
    $sensor = Sensor::factory()->create();
    watchSensor($sensor, [
        'label' => 'Câmara 1',
        'direction' => AlarmDirection::Low,
        'threshold' => 2.0,
        'trigger_after' => 0,
        'clear_after' => 120,
    ]);

    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:00:00+00', 1.0)]);

    expect(AlarmPeriod::sole())
        ->type->toBe(AlarmType::Threshold)
        ->label->toBe('Câmara 1')
        ->direction->toBe(AlarmDirection::Low)
        ->threshold->toBe(2.0)
        ->trigger_after->toBe(0)
        ->clear_after->toBe(120);
});

it('records no period for a monitor that never entered alarm', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['trigger_after' => 0]);

    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:00:00+00', 7.0)]);

    expect($watch->refresh()->status)->toBe(AlarmStatus::Ok)
        ->and(AlarmPeriod::count())->toBe(0);
});

it('leaves the monitor of an archived sensor alone', function () {
    $sensor = Sensor::factory()->create();
    $watch = watchSensor($sensor, ['trigger_after' => 0]);
    Sensor::whereKey($sensor->id)->update(['archived_at' => now()]);

    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:00:00+00', 9.0)]);

    expect($watch->refresh()->status)->toBe(AlarmStatus::Waiting)
        ->and(AlarmPeriod::count())->toBe(0);
});

it('never closes a period before it started', function () {
    $sensor = Sensor::factory()->create();
    watchSensor($sensor, ['trigger_after' => 0]);

    // A device five minutes ahead of us. The cursor is capped at our clock, so
    // the next reading is older than the period's start and still not filtered.
    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:20:00+00', 9.0)]);
    app(EvaluateAlarms::class)->handle([alarmReading($sensor, '2026-09-23 10:16:00+00', 7.0)]);

    $period = AlarmPeriod::sole();

    expect($period->started_at->toIso8601String())->toBe('2026-09-23T10:20:00+00:00')
        ->and($period->ended_at->toIso8601String())->toBe('2026-09-23T10:20:00+00:00');
});
