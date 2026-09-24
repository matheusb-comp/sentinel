<?php

namespace App\Alarms;

/**
 * What a rule watches: a value against a threshold, or the absence of readings.
 */
enum AlarmType: string
{
    case Threshold = 'threshold';
    case NoData = 'no_data';
}
