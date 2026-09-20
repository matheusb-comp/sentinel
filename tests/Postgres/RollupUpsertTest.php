<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement(<<<'SQL'
        CREATE TABLE probe_rollups (
            bucket timestamptz(6) NOT NULL,
            sensor_id bigint NOT NULL,
            min_value double precision NOT NULL,
            max_value double precision NOT NULL,
            sum_value double precision NOT NULL,
            sample_count bigint NOT NULL,
            PRIMARY KEY (sensor_id, bucket)
        ) PARTITION BY RANGE (bucket)
    SQL);

    DB::statement(<<<'SQL'
        CREATE TABLE probe_rollups_p20260920 PARTITION OF probe_rollups
        FOR VALUES FROM ('2026-09-20 00:00:00+00') TO ('2026-09-21 00:00:00+00')
    SQL);
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS probe_rollups CASCADE');
});

function foldReading(float $value): void
{
    DB::statement(<<<'SQL'
        INSERT INTO probe_rollups (bucket, sensor_id, min_value, max_value, sum_value, sample_count)
        VALUES ('2026-09-20 10:00:00+00', 1, ?, ?, ?, 1)
        ON CONFLICT (sensor_id, bucket) DO UPDATE SET
            min_value = LEAST(probe_rollups.min_value, EXCLUDED.min_value),
            max_value = GREATEST(probe_rollups.max_value, EXCLUDED.max_value),
            sum_value = probe_rollups.sum_value + EXCLUDED.sum_value,
            sample_count = probe_rollups.sample_count + EXCLUDED.sample_count
    SQL, [$value, $value, $value]);
}

it('folds readings into one bucket row on a partitioned table', function () {
    foldReading(22.4);
    foldReading(22.9);
    foldReading(21.8);

    $row = DB::table('probe_rollups')->first();

    expect(DB::table('probe_rollups')->count())->toBe(1)
        ->and((int) $row->sample_count)->toBe(3)
        ->and($row->min_value)->toEqualWithDelta(21.8, 0.000001)
        ->and($row->max_value)->toEqualWithDelta(22.9, 0.000001)
        ->and($row->sum_value)->toEqualWithDelta(67.1, 0.000001);
});

it('keeps the folded row inside the partition that covers its bucket', function () {
    foldReading(22.4);
    foldReading(22.9);

    expect(DB::table('probe_rollups_p20260920')->count())->toBe(1);
});
