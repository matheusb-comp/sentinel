<?php

use App\Actions\Alarms\CloseAlarmPeriods;
use App\Alarms\AlarmEndReason;
use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmPeriod;
use App\Models\AlarmRule;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('locks the monitors before reading their periods, not when deleting them', function () {
    config(['database.connections.rival' => config('database.connections.pgsql_testing')]);
    ['device' => $device] = registerDevice(['temp']);
    $monitor = AlarmMonitor::factory()->create([
        'alarm_rule_id' => AlarmRule::factory()->create(['company_id' => $device->company_id])->id,
        'sensor_id' => $device->sensors()->sole()->id,
        'status' => AlarmStatus::Alarm,
        'evaluated_through' => now()->subMinutes(3),
    ]);
    AlarmPeriod::factory()->forMonitor($monitor)->create();

    // Stands in for an evaluation holding the monitor, about to open a period.
    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->table('alarm_monitors')->where('id', $monitor->id)->lockForUpdate()->get();

    // Gives up instead of waiting, so the statement that blocks is the one this
    // test reads back.
    DB::statement("set lock_timeout = '300ms'");

    $blocked = null;

    try {
        DB::transaction(function () use ($monitor): void {
            app(CloseAlarmPeriods::class)->handle([$monitor->id], AlarmEndReason::Unwatched);

            AlarmMonitor::whereIn('id', [$monitor->id])->delete();
        });
    } catch (QueryException $exception) {
        $blocked = $exception->getSql();
    } finally {
        DB::statement('set lock_timeout = 0');
        $rival->rollBack();
        DB::purge('rival');
    }

    // Blocking only at the delete would let the periods be read before the
    // evaluation commits: nothing to close, and then the monitor gone with a
    // period left open behind it.
    expect($blocked)->toStartWith('select')
        ->and(AlarmPeriod::sole()->ended_at)->toBeNull();
});

it('locks the rule before reading the monitors its delete will take', function () {
    config(['database.connections.rival' => config('database.connections.pgsql_testing')]);
    $rule = AlarmRule::factory()->create(['company_id' => Company::factory()->create()->id]);

    // What an insert into alarm_monitors takes on the rule row for its foreign
    // key, so it stands in for a sensor being put under the rule right now.
    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->select('select id from alarm_rules where id = ? for key share', [$rule->id]);

    DB::statement("set lock_timeout = '300ms'");

    $blocked = null;

    try {
        DB::transaction(fn () => app(CloseAlarmPeriods::class)->forRule($rule, AlarmEndReason::Unwatched));
    } catch (QueryException $exception) {
        $blocked = $exception->getSql();
    } finally {
        DB::statement('set lock_timeout = 0');
        $rival->rollBack();
        DB::purge('rival');
    }

    expect($blocked)->toContain('for update');
});
