<?php

return [
    /*
     * How "old" a reading can be and still be considered for alarm evaluation.
     *
     * Value is in seconds, and overridable on each AlarmRule.
     */
    'max_reading_age' => (int) env('ALARMS_MAX_READING_AGE', 3600),
];
