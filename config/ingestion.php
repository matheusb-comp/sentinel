<?php

return [
    /*
     * Refuses `time` ahead of the server clock by more than `future_tolerance`,
     * or older than `backfill`. Both are intervals as Carbon reads them.
     *
     * Series maintenance creates partitions back to `backfill`, so it has to stay
     * below the retention of every table in config/series.php, and
     * `future_tolerance` within the partitions their `premake` keeps ahead.
     */
    'future_tolerance' => env('INGESTION_FUTURE_TOLERANCE', '5 minutes'),

    'backfill' => env('INGESTION_BACKFILL', '7 days'),

    /*
     * Every registration issues a device a new token and keeps the earlier ones,
     * so this bounds how many tokens a device can hold.
     */
    'max_tokens_per_device' => (int) env('INGESTION_MAX_TOKENS_PER_DEVICE', 20),

    /*
     * The most sensors a device can declare in one request, registering or
     * synchronizing. Reconciling compares each declared key with every other
     * one, and writes once per sensor that changed.
     */
    'max_sensors_per_device' => (int) env('INGESTION_MAX_SENSORS_PER_DEVICE', 1000),
];
