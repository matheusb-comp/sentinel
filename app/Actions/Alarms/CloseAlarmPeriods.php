<?php

namespace App\Actions\Alarms;

use App\Alarms\AlarmEndReason;
use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmPeriod;
use App\Models\AlarmRule;

/**
 * Ends the open period of every monitor that is about to stop seeing readings.
 *
 * A period is closed at the monitor's `evaluated_through`, the last instant it
 * had a reading for, because nothing is known about the sensor between that and
 * now. Dating the end at our own clock would claim otherwise.
 *
 * A monitor that outlives its period — the sensor was archived, the watch itself
 * was not removed — goes back to `waiting`. Left in `alarm` with no open period,
 * a sensor that comes back still out of range would never have that alarm
 * recorded, because a period is only written when the status moves.
 *
 * Runs inside the caller's transaction, and takes the same lock the evaluation
 * takes. Without it, a period opened by an evaluation that has not committed yet
 * is invisible here, and stays open forever once the monitor is gone.
 */
class CloseAlarmPeriods
{
    /**
     * The periods of every monitor of a rule, for a caller that is about to
     * delete it and let the cascade take those monitors.
     *
     * The rule is locked first because an insert into alarm_monitors takes a key
     * share on its row, which this lock conflicts with. Without it a monitor
     * added after the ids were read is still cascade-deleted, and its open
     * period is left pointing at nothing, with no way to ever close it.
     */
    public function forRule(AlarmRule $rule, AlarmEndReason $reason): void
    {
        AlarmRule::whereKey($rule->getKey())->lockForUpdate()->sole();

        $this->handle($rule->monitors()->pluck('id')->all(), $reason);
    }

    /**
     * @param  list<int>  $monitorIds
     */
    public function handle(array $monitorIds, AlarmEndReason $reason): void
    {
        if ($monitorIds === []) {
            return;
        }

        // A fixed order, so two callers over overlapping monitors queue up
        // instead of deadlocking.
        $monitors = AlarmMonitor::whereIn('id', $monitorIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $periods = AlarmPeriod::whereIn('alarm_monitor_id', $monitors->keys())
            ->whereNull('ended_at')
            ->get();

        foreach ($periods as $period) {
            $period->endAt($monitors[$period->alarm_monitor_id]->evaluated_through, $reason);
            $period->save();
        }

        if ($reason === AlarmEndReason::Archived) {
            AlarmMonitor::whereIn('id', $monitors->keys())->update([
                'status' => AlarmStatus::Waiting,
                'status_since' => now(),
                'pending_since' => null,
                'next_check_at' => null,
            ]);
        }
    }
}
