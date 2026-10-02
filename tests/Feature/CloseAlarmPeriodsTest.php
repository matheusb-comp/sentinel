<?php

use App\Actions\Alarms\CloseAlarmPeriods;
use App\Actions\Devices\ArchiveDevice;
use App\Actions\Devices\SyncDeviceSensors;
use App\Alarms\AlarmEndReason;
use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmPeriod;
use App\Models\AlarmRule;
use App\Models\Company;
use App\Models\Device;
use App\Models\Sensor;

/**
 * A sensor of the company watched by a rule of its own, in alarm, with the
 * period still open and the monitor having read up to three minutes ago.
 */
function watchInAlarm(Company $company, ?Sensor $sensor = null): AlarmMonitor
{
    $monitor = AlarmMonitor::factory()->create([
        'alarm_rule_id' => AlarmRule::factory()->create(['company_id' => $company->id])->id,
        'sensor_id' => ($sensor ?? Sensor::factory()->create([
            'device_id' => Device::factory()->create(['company_id' => $company->id]),
        ]))->id,
        'status' => AlarmStatus::Alarm,
        'evaluated_through' => now()->subMinutes(3),
    ]);

    AlarmPeriod::factory()->forMonitor($monitor)->create();

    return $monitor;
}

it('closes at the last instant the monitor had read', function () {
    $monitor = watchInAlarm(Company::factory()->create());

    app(CloseAlarmPeriods::class)->handle([$monitor->id], AlarmEndReason::Unwatched);

    expect(AlarmPeriod::sole())
        ->ended_reason->toBe(AlarmEndReason::Unwatched)
        ->ended_at->toIso8601String()->toBe($monitor->evaluated_through->toIso8601String());
});

it('leaves a period that was already closed alone', function () {
    $monitor = AlarmMonitor::factory()->create();
    AlarmPeriod::factory()->forMonitor($monitor)->closed()->create();

    app(CloseAlarmPeriods::class)->handle([$monitor->id], AlarmEndReason::Unwatched);

    expect(AlarmPeriod::sole()->ended_reason)->toBe(AlarmEndReason::Transition);
});

it('closes the period of an unwatched sensor', function () {
    $company = actingAsMember();
    $monitor = watchInAlarm($company);

    $this->deleteJson(
        "/api/v1/companies/{$company->slug}/alarm-rules/{$monitor->rule->uuid}/monitors",
        ['sensor_uuids' => [$monitor->sensor->uuid]],
    )->assertNoContent();

    expect(AlarmMonitor::find($monitor->id))->toBeNull()
        ->and(AlarmPeriod::sole()->ended_reason)->toBe(AlarmEndReason::Unwatched);
});

it('leaves another rule watching the same sensor alone', function () {
    $company = actingAsMember();
    $unwatched = watchInAlarm($company);
    $kept = watchInAlarm($company, $unwatched->sensor);

    $this->deleteJson(
        "/api/v1/companies/{$company->slug}/alarm-rules/{$unwatched->rule->uuid}/monitors",
        ['sensor_uuids' => [$unwatched->sensor->uuid]],
    )->assertNoContent();

    expect(AlarmPeriod::where('alarm_monitor_id', $kept->id)->sole()->ended_at)->toBeNull();
});

it('closes the periods of every monitor a deleted rule had', function () {
    $company = actingAsMember();
    $first = watchInAlarm($company);
    $second = AlarmMonitor::factory()->create([
        'alarm_rule_id' => $first->alarm_rule_id,
        'sensor_id' => Sensor::factory()->create([
            'device_id' => Device::factory()->create(['company_id' => $company->id]),
        ])->id,
        'status' => AlarmStatus::Alarm,
        'evaluated_through' => now()->subMinutes(3),
    ]);
    AlarmPeriod::factory()->forMonitor($second)->create();

    $this->deleteJson("/api/v1/companies/{$company->slug}/alarm-rules/{$first->rule->uuid}")
        ->assertNoContent();

    expect(AlarmPeriod::whereNull('ended_at')->count())->toBe(0)
        ->and(AlarmPeriod::pluck('ended_reason')->unique()->all())->toBe([AlarmEndReason::Unwatched]);
});

it('closes the periods of a sensor the device stopped declaring', function () {
    ['device' => $device] = registerDevice(['temp', 'hum']);
    $monitor = watchInAlarm($device->company, $device->sensors()->where('key', 'hum')->sole());

    app(SyncDeviceSensors::class)->handle($device, [['key' => 'temp', 'description' => null]]);

    expect(AlarmPeriod::sole())
        ->ended_reason->toBe(AlarmEndReason::Archived)
        ->ended_at->toIso8601String()->toBe($monitor->evaluated_through->toIso8601String());
});

it('closes the periods of every sensor of an archived device', function () {
    ['device' => $device] = registerDevice(['temp', 'hum']);
    $device->sensors->each(fn (Sensor $sensor) => watchInAlarm($device->company, $sensor));

    app(ArchiveDevice::class)->handle($device);

    expect(AlarmPeriod::count())->toBe(2)
        ->and(AlarmPeriod::whereNull('ended_at')->count())->toBe(0)
        ->and(AlarmPeriod::pluck('ended_reason')->unique()->all())->toBe([AlarmEndReason::Archived]);
});

it('sends the monitor of an archived sensor back to waiting', function () {
    ['device' => $device] = registerDevice(['temp']);
    $monitor = watchInAlarm($device->company, $device->sensors()->sole());
    $monitor->update(['pending_since' => now()->subMinute(), 'next_check_at' => now()->addMinute()]);

    app(SyncDeviceSensors::class)->handle($device, []);

    expect($monitor->refresh())
        ->status->toBe(AlarmStatus::Waiting)
        ->pending_since->toBeNull()
        ->next_check_at->toBeNull()
        ->evaluated_through->not->toBeNull();
});

it('leaves an unwatched monitor untouched when the rule survives', function () {
    $monitor = watchInAlarm(Company::factory()->create());

    app(CloseAlarmPeriods::class)->handle([$monitor->id], AlarmEndReason::Unwatched);

    expect($monitor->refresh()->status)->toBe(AlarmStatus::Alarm);
});

it('never ends a period before it started', function () {
    $monitor = watchInAlarm(Company::factory()->create());
    // A device clock ahead of ours: the monitor read up to its own instant, and
    // the cursor is capped at ours.
    $monitor->update(['evaluated_through' => now()->subHour()]);

    app(CloseAlarmPeriods::class)->handle([$monitor->id], AlarmEndReason::Archived);

    $period = AlarmPeriod::sole();

    expect($period->ended_at->toIso8601String())->toBe($period->started_at->toIso8601String());
});
