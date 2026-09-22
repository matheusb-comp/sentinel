<?php

return [
    /*
     * How long a maintenance statement waits for its lock before giving up.
     *
     * Creating or dropping a partition takes an ACCESS EXCLUSIVE lock on the
     * parent table, so giving up keeps ingestion running.
     */
    'lock_timeout' => '3s',

    /*
     * Time range partitioned tables, keyed by table name: `series:setup` creates
     * the missing ones and `series:maintain-partitions` keeps their partitions.
     *
     *   bucket     rollup tables only: the width each reading is folded into.
     *              Without it, a table holds the raw readings. It has to divide
     *              a day evenly, or an hour for hourly partitions: otherwise a
     *              bucket can start in the partition before the one holding its
     *              readings, which may not exist.
     *   partition  width of each partition: 1 hour, 1 day, 1 week or 1 month
     *   premake    how many partitions to keep created ahead of the current one;
     *              maintenance runs daily, so this has to reach well past a day
     *   retention  how long to keep a partition after its range ends; null
     *              keeps every partition. It has to stay above
     *              `ingestion.backfill`, the window maintenance also creates
     *              partitions back to.
     *
     * A table name is lowercase and without a dot, since the statements use it
     * unquoted, and short enough for its partition names to fit in 63
     * characters. Renaming a table creates a new, empty one and leaves the old
     * one with its rows.
     *
     * A partition name records the start of its range, not its width, so a table
     * that already has partitions cannot have its `partition` changed in place:
     * the existing names would be read as ranges of the new width, and retention
     * would drop them on the wrong date. Changing it means draining the table
     * first.
     */
    'tables' => [
        'readings' => [
            'partition' => '1 day',
            'premake' => 14,
            'retention' => null,
        ],
        'readings_5m' => [
            'bucket' => '5 minutes',
            'partition' => '1 week',
            'premake' => 2,
            'retention' => null,
        ],
        'readings_1h' => [
            'bucket' => '1 hour',
            'partition' => '1 month',
            'premake' => 2,
            'retention' => null,
        ],
    ],
];
