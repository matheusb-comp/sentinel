<?php

namespace App\Actions\Alarms;

use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use Carbon\CarbonImmutable;

/**
 * Moves a monitor with one reading of the sensor it watches.
 *
 * Pure: it reads no clock and touches no database. `$now` is the instant the
 * caller is evaluating at, and the monitor is left changed in memory for the
 * caller to save.
 *
 * Entering and leaving alarm are the same operation. A value on the side
 * opposite the status stamps `pending_since`, and the duration of that side —
 * `trigger_after` leaving `ok`, `clear_after` leaving `alarm` — decides when the
 * status flips. A value back on the side of the status clears the stamp, so any
 * single reading resets the count.
 *
 * The duration is measured between readings, on the device's own clock, so a
 * batch that arrives late is still judged by how long the equipment was out of
 * range. `next_check_at` is anchored on ours instead, which is why a device
 * whose clock is offset cannot move the schedule with it.
 */
class EvaluateAlarmRule
{
    /**
     * @return bool whether the status changed
     */
    public function handle(
        AlarmRule $rule,
        AlarmMonitor $monitor,
        CarbonImmutable $time,
        float $value,
        CarbonImmutable $now,
    ): bool {
        if ($monitor->evaluated_through !== null && ! $time->greaterThan($monitor->evaluated_through)) {
            return false;
        }

        if ($time->lessThan($now->subSeconds($rule->maxReadingAge()))) {
            return false;
        }

        if ($monitor->status === AlarmStatus::Waiting) {
            $monitor->status = AlarmStatus::Ok;
            $monitor->status_since = $time;
        }

        $breaching = $rule->direction->breaches($value, $rule->threshold);
        $changed = false;

        if ($monitor->status === AlarmStatus::Ok ? $breaching : ! $breaching) {
            $monitor->pending_since ??= $time;

            $duration = $monitor->status === AlarmStatus::Ok ? $rule->trigger_after : $rule->clear_after;

            // A reading older than the stamp has spent no time on that side:
            // the stamp came from a reading further ahead of it.
            $elapsed = max(0, (int) $monitor->pending_since->diffInSeconds($time));

            if ($elapsed >= $duration) {
                $monitor->status = $monitor->status === AlarmStatus::Ok ? AlarmStatus::Alarm : AlarmStatus::Ok;
                $monitor->status_since = $time;
                $monitor->pending_since = null;
                $changed = true;
            } else {
                $monitor->next_check_at = $now->addSeconds($duration - $elapsed);
            }
        } else {
            $monitor->pending_since = null;
        }

        if ($monitor->pending_since === null) {
            $monitor->next_check_at = null;
        }

        $monitor->evaluated_through = $time->lessThan($now) ? $time : $now;
        $monitor->last_value = $value;

        return $changed;
    }
}
