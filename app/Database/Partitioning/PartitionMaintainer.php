<?php

namespace App\Database\Partitioning;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;

/**
 * Creates and drops time range partitions for the tables in config/series.php.
 *
 * Partition names and the configuration vocabulary is from pg_partman, so a
 * table managed here and a table managed by that extension are interchangeable.
 *
 * Partition bounds are never read back: the name carries the start of the range
 * and the interval gives the end. Partitions whose name is not in that format
 * are left alone.
 */
class PartitionMaintainer
{
    public function maintain(
        string $table,
        PartitionInterval $interval,
        int $premake,
        ?string $retention = null,
    ): PartitionReport {
        $now = CarbonImmutable::now('UTC');

        return new PartitionReport(
            created: $this->createUpcoming($table, $interval, $premake, $now),
            dropped: $retention === null ? [] : $this->dropExpired($table, $interval, $retention, $now),
            coveredUntil: $this->coveredUntil($table, $interval, $now),
        );
    }

    /** @return list<string> */
    private function createUpcoming(
        string $table,
        PartitionInterval $interval,
        int $premake,
        CarbonImmutable $now,
    ): array {
        $existing = $this->existingStarts($table, $interval);
        $created = [];
        $start = $interval->start($now);

        for ($i = 0; $i <= $premake; $i++) {
            $name = $this->name($table, $interval, $start);

            if (! array_key_exists($name, $existing)) {
                // Partition bounds cannot be bound as parameters, so they are
                // formatted into the statement.
                $this->runWithLockTimeout(sprintf(
                    "CREATE TABLE %s PARTITION OF %s FOR VALUES FROM ('%s') TO ('%s')",
                    $name,
                    $table,
                    $start->format('Y-m-d H:i:sP'),
                    $interval->next($start)->format('Y-m-d H:i:sP'),
                ));

                $created[] = $name;
            }

            $start = $interval->next($start);
        }

        return $created;
    }

    /** @return list<string> */
    private function dropExpired(
        string $table,
        PartitionInterval $interval,
        string $retention,
        CarbonImmutable $now,
    ): array {
        $keepFrom = $now->sub(CarbonInterval::make($retention));
        $dropped = [];

        foreach ($this->existingStarts($table, $interval) as $name => $start) {
            if ($interval->next($start)->greaterThan($keepFrom)) {
                continue;
            }

            $this->runWithLockTimeout(sprintf('DROP TABLE %s', $name));

            $dropped[] = $name;
        }

        return $dropped;
    }

    /**
     * The end of the unbroken run of partitions starting at the current one.
     *
     * Null when the current range has no partition at all. Stopping at the first
     * gap is what makes this usable as a health signal.
     */
    private function coveredUntil(
        string $table,
        PartitionInterval $interval,
        CarbonImmutable $now,
    ): ?CarbonImmutable {
        $existing = $this->existingStarts($table, $interval);
        $start = $interval->start($now);

        if (! array_key_exists($this->name($table, $interval, $start), $existing)) {
            return null;
        }

        while (array_key_exists($this->name($table, $interval, $interval->next($start)), $existing)) {
            $start = $interval->next($start);
        }

        return $interval->next($start);
    }

    /** @return array<string, CarbonImmutable> partition name => start of its range */
    private function existingStarts(string $table, PartitionInterval $interval): array
    {
        $prefix = $table.'_p';

        $rows = DB::select(<<<'SQL'
            SELECT child.relname AS name
            FROM pg_inherits
            JOIN pg_class child ON child.oid = pg_inherits.inhrelid
            WHERE pg_inherits.inhparent = to_regclass(?)
        SQL, [$table]);

        $starts = [];

        foreach ($rows as $row) {
            if (! str_starts_with($row->name, $prefix)) {
                continue;
            }

            $start = $interval->parse(substr($row->name, strlen($prefix)));

            if ($start !== null) {
                $starts[$row->name] = $start;
            }
        }

        return $starts;
    }

    /**
     * Runs one partition statement under a lock timeout.
     *
     * Creating or dropping a partition takes an ACCESS EXCLUSIVE lock on the
     * parent, and every read and write arriving while it waits queues behind it.
     * Failing is the lesser outcome: the partitions already in hand cover the
     * next few days, so the following run recovers.
     *
     * The timeout is transaction scoped, so Postgres restores it whether the
     * statement succeeds or not.
     */
    private function runWithLockTimeout(string $statement): void
    {
        DB::transaction(function () use ($statement): void {
            DB::select("SELECT set_config('lock_timeout', ?, true)", [config('series.lock_timeout')]);
            DB::statement($statement);
        });
    }

    private function name(string $table, PartitionInterval $interval, CarbonImmutable $start): string
    {
        return $table.'_p'.$interval->suffix($start);
    }
}
