<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creates the series tables of config/series.php that are not there yet.
 */
class SetupSeries extends Command
{
    protected $signature = 'series:setup';

    protected $description = 'Create the series tables declared in config/series.php';

    public function handle(): int
    {
        $failed = false;

        foreach (config('series.tables') as $table => $settings) {
            try {
                if ($this->exists($table)) {
                    continue;
                }

                DB::statement($this->createStatement($table, $settings));
                $this->line("{$table}: created");
            } catch (Throwable $e) {
                // One unusable table must not leave the others uncreated.
                report($e);
                $this->error(sprintf('%s: %s', $table, $e->getMessage()));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function exists(string $table): bool
    {
        return DB::selectOne('SELECT to_regclass(?) AS relation', [$table])->relation !== null;
    }

    /**
     * A table with a bucket holds rollups; one without holds the raw readings.
     *
     * The primary key is what ON CONFLICT folds a resent batch into and what the
     * package's RLS manager requires, and on a partitioned table it has to
     * include the partitioning column. RESTRICT keeps a sensor delete from
     * deleting millions of readings inside one transaction.
     *
     * @param  array{bucket?: string}  $settings
     */
    private function createStatement(string $table, array $settings): string
    {
        if (isset($settings['bucket'])) {
            return <<<SQL
                CREATE TABLE {$table} (
                    bucket timestamptz(6) NOT NULL,
                    sensor_id bigint NOT NULL REFERENCES sensors (id) ON DELETE RESTRICT,
                    value_min double precision NOT NULL,
                    value_max double precision NOT NULL,
                    value_sum double precision NOT NULL,
                    sample_count bigint NOT NULL,
                    PRIMARY KEY (sensor_id, bucket)
                ) PARTITION BY RANGE (bucket)
                SQL;
        }

        return <<<SQL
            CREATE TABLE {$table} (
                time timestamptz(6) NOT NULL,
                created_at timestamptz(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sensor_id bigint NOT NULL REFERENCES sensors (id) ON DELETE RESTRICT,
                value double precision NOT NULL,
                PRIMARY KEY (sensor_id, time)
            ) PARTITION BY RANGE (time)
            SQL;
    }
}
