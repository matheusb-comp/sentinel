<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('partitions every series table by range', function (string $table) {
    $strategy = DB::scalar(
        'SELECT partstrat FROM pg_partitioned_table WHERE partrelid = to_regclass(?)',
        [$table]
    );

    expect($strategy)->toBe('r');
})->with(['readings', 'readings_5m', 'readings_1h']);

it('leaves the rows of a table that is already there alone', function () {
    $sensor = seedSeriesSensor();

    DB::table('readings')->insert([
        'time' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.uP'),
        'sensor_id' => $sensor->id,
        'value' => 1.0,
    ]);

    expect(Artisan::call('series:setup'))->toBe(0)
        ->and(DB::table('readings')->where('sensor_id', $sensor->id)->count())->toBe(1);
});

it('refuses a reading with no partition for its instant', function () {
    $sensor = seedSeriesSensor();

    $insert = fn () => DB::table('readings')->insert([
        // Before any range the maintainer creates, and older than any history.
        'time' => CarbonImmutable::parse('1980-01-01 00:00:00', 'UTC'),
        'sensor_id' => $sensor->id,
        'value' => 1.0,
    ]);

    expect($insert)->toThrow(QueryException::class, 'no partition of relation');
});

it('refuses to delete a sensor that has readings', function () {
    $sensor = seedSeriesSensor();

    DB::table('readings')->insert([
        'time' => CarbonImmutable::now('UTC'),
        'sensor_id' => $sensor->id,
        'value' => 1.0,
    ]);

    expect(fn () => $sensor->delete())->toThrow(QueryException::class);
});

it('derives the tenant policy through sensors and devices', function (string $table) {
    Artisan::call('tenants:rls');

    $policy = DB::table('pg_policies')->where('tablename', $table)->value('qual');

    expect($policy)->toContain('FROM sensors')
        ->and($policy)->toContain('FROM devices')
        ->and($policy)->toContain('::bigint')
        ->and($policy)->not->toContain('(sensor_id)::text');
})->with(['readings', 'readings_5m', 'readings_1h']);
