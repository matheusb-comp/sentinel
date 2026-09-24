<?php

return [
    /*
     * How "old" a reading can be and still be considered for alarm evaluation.
     *
     * Value is in seconds, and overridable on each AlarmRule.
     */
    'max_reading_age' => (int) env('ALARMS_MAX_READING_AGE', 3600),

    /*
     * The most sensors one request can put under a rule. Each one in the list is
     * looked up on its own, so this bounds the queries a single request costs.
     */
    'max_sensors_per_request' => (int) env('ALARMS_MAX_SENSORS_PER_REQUEST', 100),
];
