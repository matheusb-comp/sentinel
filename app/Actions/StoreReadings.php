<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Stores raw readings and folds them into every table of config/series.php
 * that has a bucket, in one statement, so a failing rollup takes the raw rows
 * with it.
 *
 * The rollups read the rows the insert actually wrote, so a reading already
 * stored is not counted again: resending a batch does not move an average.
 */
class StoreReadings
{
    /**
     * `time` is a string formatted as `Y-m-d H:i:s.uP`: a Carbon binding loses
     * the fraction. `value` is bound as the shortest string that reads back as
     * the same double, because a float binding keeps 14 significant digits.
     *
     * @param  list<array{sensor_id: int, time: string, value: float}>  $readings
     * @return int how many readings were new
     */
    public function handle(array $readings): int
    {
        if ($readings === []) {
            return 0;
        }

        $bindings = [];
        $tuples = [];

        foreach ($readings as $reading) {
            $tuples[] = '(?, ?, ?)';
            $bindings[] = $reading['time'];
            $bindings[] = $reading['sensor_id'];
            $bindings[] = var_export($reading['value'], true);
        }

        $rollups = [];

        foreach (config('series.tables') as $table => $settings) {
            if (! isset($settings['bucket'])) {
                continue;
            }

            $rollups[] = $this->rollup($table);
            $bindings[] = $settings['bucket'];
        }

        $statement = sprintf(
            "WITH inserted AS (\n%s\n)%s\nSELECT count(*) AS written FROM inserted",
            $this->insert($tuples),
            $rollups === [] ? '' : ",\n".implode(",\n", $rollups),
        );

        return (int) DB::selectOne($statement, $bindings)->written;
    }

    /**
     * @param  list<string>  $tuples
     */
    private function insert(array $tuples): string
    {
        return sprintf(
            'INSERT INTO readings (time, sensor_id, value) VALUES %s '
            .'ON CONFLICT (sensor_id, time) DO NOTHING RETURNING time, sensor_id, value',
            implode(', ', $tuples),
        );
    }

    /**
     * A data modifying CTE, which Postgres runs to completion whether or not the
     * outer query reads it: that is why none of them is referenced at the end.
     */
    private function rollup(string $table): string
    {
        return <<<SQL
            rollup_{$table} AS (
                INSERT INTO {$table} (bucket, sensor_id, value_min, value_max, value_sum, sample_count)
                SELECT date_bin(?::interval, time, TIMESTAMPTZ '2000-01-01'), sensor_id,
                       min(value), max(value), sum(value), count(*)
                FROM inserted
                GROUP BY 1, sensor_id
                ON CONFLICT (sensor_id, bucket) DO UPDATE SET
                    value_min = LEAST({$table}.value_min, EXCLUDED.value_min),
                    value_max = GREATEST({$table}.value_max, EXCLUDED.value_max),
                    value_sum = {$table}.value_sum + EXCLUDED.value_sum,
                    sample_count = {$table}.sample_count + EXCLUDED.sample_count
            )
            SQL;
    }
}
