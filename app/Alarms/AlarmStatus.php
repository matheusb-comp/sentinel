<?php

namespace App\Alarms;

/**
 * Where the evaluation of a rule over a sensor stands.
 *
 * `Waiting` is a monitor that has not seen a reading yet. A transition in
 * flight is not a status: it is the monitor's `pending_since`.
 */
enum AlarmStatus: string
{
    case Alarm = 'alarm';
    case Ok = 'ok';
    case Waiting = 'waiting';
}
