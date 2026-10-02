<?php

namespace App\Alarms;

/**
 * Why an alarm period ended.
 *
 * `Transition` is the only one that means the sensor came back into range. The
 * other two end the period without anything being resolved: `Unwatched` when the
 * monitor is removed, `Archived` when the sensor stops being read and its
 * monitor starts over.
 */
enum AlarmEndReason: string
{
    case Transition = 'transition';
    case Unwatched = 'unwatched';
    case Archived = 'archived';
}
