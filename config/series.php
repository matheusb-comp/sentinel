<?php

return [
    /*
     * Time range partitioned tables, keyed by table name.
     *
     *   partition  width of each partition: 1 hour, 1 day, 1 week or 1 month
     *   premake    how many partitions to keep created ahead of the current one
     *   retention  how long to keep a partition after its range ends;
     *              null keeps every partition
     *
     * A partition name records the start of its range, not its width, so a table
     * that already has partitions cannot have its `partition` changed in place:
     * the existing names would be read as ranges of the new width, and retention
     * would drop them on the wrong date. Changing it means draining the table
     * first.
     */
    'tables' => [
        //
    ],
];
