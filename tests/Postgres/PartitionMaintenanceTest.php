<?php

use App\Database\Partitioning\PartitionInterval;
use App\Database\Partitioning\PartitionMaintainer;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement(<<<'SQL'
        CREATE TABLE probe_readings (
            time timestamptz(6) NOT NULL,
            sensor_id bigint NOT NULL,
            value double precision NOT NULL,
            PRIMARY KEY (sensor_id, time)
        ) PARTITION BY RANGE (time)
    SQL);
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS probe_readings CASCADE');
});

it('creates the current partition and the premake window ahead of it', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));

    app(PartitionMaintainer::class)->maintain('probe_readings', PartitionInterval::Day, premake: 3);

    expect(probePartitions())->toBe([
        'probe_readings_p20260920',
        'probe_readings_p20260921',
        'probe_readings_p20260922',
        'probe_readings_p20260923',
    ]);
});

it('creates nothing on a second run', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));
    $maintainer = app(PartitionMaintainer::class);

    $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 3);
    $report = $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 3);

    expect($report->created)->toBe([]);
});

it('reports how far ahead the table is covered', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));

    $report = app(PartitionMaintainer::class)->maintain('probe_readings', PartitionInterval::Day, premake: 3);

    expect($report->coveredUntil->toIso8601String())->toBe('2026-09-24T00:00:00+00:00');
});

it('routes a reading into the partition that covers its instant', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));
    app(PartitionMaintainer::class)->maintain('probe_readings', PartitionInterval::Day, premake: 1);

    DB::table('probe_readings')->insert([
        'time' => '2026-09-21 08:30:00+00',
        'sensor_id' => 1,
        'value' => 22.4,
    ]);

    expect(DB::table('probe_readings_p20260921')->count())->toBe(1)
        ->and(DB::table('probe_readings_p20260920')->count())->toBe(0);
});

it('refuses a reading that no partition covers', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));
    app(PartitionMaintainer::class)->maintain('probe_readings', PartitionInterval::Day, premake: 1);

    $insert = fn () => DB::table('probe_readings')->insert([
        'time' => '2026-10-15 00:00:00+00',
        'sensor_id' => 1,
        'value' => 22.4,
    ]);

    expect($insert)->toThrow(QueryException::class, 'no partition of relation');
});

it('drops a partition whose range ended before the retention window', function () {
    $maintainer = app(PartitionMaintainer::class);

    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00', 'UTC'));
    $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00:00', 'UTC'));
    $report = $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0, retention: '7 days');

    expect($report->dropped)->toBe(['probe_readings_p20260910'])
        ->and(probePartitions())->toBe(['probe_readings_p20260920']);
});

it('keeps a partition whose range has not fully expired', function () {
    $maintainer = app(PartitionMaintainer::class);

    $this->travelTo(CarbonImmutable::parse('2026-09-13 12:00:00', 'UTC'));
    $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00:00', 'UTC'));
    $report = $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0, retention: '7 days');

    expect($report->dropped)->toBe([])
        ->and(probePartitions())->toContain('probe_readings_p20260913');
});

it('keeps every partition when no retention is configured', function () {
    $maintainer = app(PartitionMaintainer::class);

    $this->travelTo(CarbonImmutable::parse('2026-01-01 12:00:00', 'UTC'));
    $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00:00', 'UTC'));
    $report = $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    expect($report->dropped)->toBe([])
        ->and(probePartitions())->toContain('probe_readings_p20260101');
});

it('reports coverage only up to the first gap', function () {
    $maintainer = app(PartitionMaintainer::class);

    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));
    $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    $this->travelTo(CarbonImmutable::parse('2026-09-22 13:00:00', 'UTC'));
    $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));
    $report = $maintainer->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    expect($report->coveredUntil->toIso8601String())->toBe('2026-09-21T00:00:00+00:00');
});

it('reports no coverage when the current range has no partition', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));

    $report = app(PartitionMaintainer::class)->maintain('probe_readings', PartitionInterval::Day, premake: -1);

    expect($report->coveredUntil)->toBeNull();
});

it('keeps maintaining the other tables when one of them fails', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));
    config(['series.tables' => [
        'probe_missing' => ['partition' => '1 day', 'premake' => 0, 'retention' => null],
        'probe_readings' => ['partition' => '1 day', 'premake' => 0, 'retention' => null],
    ]]);

    $this->artisan('partitions:maintain')->assertFailed();

    expect(probePartitions())->toBe(['probe_readings_p20260920']);
});

it('maintains every table listed in the series config', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));
    config(['series.tables' => [
        'probe_readings' => ['partition' => '1 day', 'premake' => 1, 'retention' => null],
    ]]);

    $this->artisan('partitions:maintain')->assertSuccessful();

    expect(probePartitions())->toBe([
        'probe_readings_p20260920',
        'probe_readings_p20260921',
    ]);
});
