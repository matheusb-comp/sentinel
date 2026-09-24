<?php

use App\Actions\Alarms\EvaluateAlarms;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('locks the monitors when it reads them, not when it writes them', function () {
    config(['database.connections.rival' => config('database.connections.pgsql_testing')]);
    ['device' => $device] = registerDevice(['temp']);
    $sensor = $device->sensors()->sole();
    $watch = AlarmMonitor::factory()->create([
        'alarm_rule_id' => AlarmRule::factory()->create(['company_id' => $device->company_id])->id,
        'sensor_id' => $sensor->id,
    ]);

    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->table('alarm_monitors')->where('id', $watch->id)->lockForUpdate()->get();

    // Gives up instead of waiting, so the statement that blocks is the one this
    // test reads back.
    DB::statement("set lock_timeout = '300ms'");

    $blocked = null;

    try {
        DB::transaction(fn () => app(EvaluateAlarms::class)->handle([
            ['sensor_id' => $sensor->id, 'time' => now()->format('Y-m-d H:i:sP'), 'value' => 9.0],
        ]));
    } catch (QueryException $exception) {
        $blocked = $exception->getSql();
    } finally {
        DB::statement('set lock_timeout = 0');
        $rival->rollBack();
        DB::purge('rival');
    }

    // Reading under the lock is what keeps a batch from computing the next
    // status out of a row another batch is about to replace. Blocking only at
    // the write would let both read the same stale monitor.
    expect($blocked)->toStartWith('select')
        ->and($watch->refresh()->last_value)->toBeNull();
});
