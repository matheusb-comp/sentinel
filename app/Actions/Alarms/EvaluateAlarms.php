<?php

namespace App\Actions\Alarms;

use App\Alarms\AlarmEndReason;
use App\Alarms\AlarmStatus;
use App\Alarms\AlarmType;
use App\Models\AlarmMonitor;
use App\Models\AlarmPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Moves every monitor the readings of one batch touch, and records what happened.
 *
 * The readings of a sensor are walked in order of their own instant, inside a
 * transaction, because the lock it takes on the monitors is what makes two
 * batches of the same sensor run one after the other instead of interleaved. The
 * alarm_periods rows are written in that same transaction, so a monitor and the
 * period it opened commit together or neither does.
 *
 * Every transition is recorded, including the ones a single batch passes through:
 * a batch can enter and leave alarm more than once, and each is a period of its
 * own. That is why the status and the pending stamp are read before each
 * evaluation instead of compared against the row that was loaded — the machine
 * clears the stamp on the transition that uses it.
 *
 * Two reads for the monitors and their rules, whatever the size of the batch.
 * Past those, the cost follows the transitions rather than the readings: a
 * monitor that did not move is not dirty and writes nothing.
 */
class EvaluateAlarms
{
    public function __construct(private EvaluateAlarmRule $evaluate) {}

    /**
     * @param  list<array{sensor_id: int, time: string, value: float}>  $readings
     */
    public function handle(array $readings): void
    {
        if ($readings === []) {
            return;
        }

        $bySensor = $this->bySensor($readings);

        (new AlarmMonitor)->getConnection()->transaction(function () use ($readings, $bySensor): void {
            $monitors = AlarmMonitor::whereIn('sensor_id', array_values(array_unique(array_column($readings, 'sensor_id'))))
                ->whereHas('rule', function (Builder $rule): void {
                    $rule->where('type', AlarmType::Threshold)->where('active', true);
                })
                // An archived sensor has its readings refused, so a batch that
                // resolved its ids before the archive must not move its monitors:
                // a period opened now would have nothing left to close it.
                ->whereHas('sensor', function (Builder $sensor): void {
                    $sensor->whereNull('archived_at');
                })
                ->with('rule')
                // A fixed order, so two batches over overlapping sensors queue up
                // instead of deadlocking.
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $now = CarbonImmutable::now('UTC');

            foreach ($monitors as $monitor) {
                foreach ($bySensor[$monitor->sensor_id] as $reading) {
                    $status = $monitor->status;
                    $pendingSince = $monitor->pending_since;

                    $this->evaluate->handle($monitor->rule, $monitor, $reading['at'], $reading['value'], $now);

                    if ($monitor->status !== $status) {
                        $this->record($monitor, $status, $pendingSince ?? $reading['at'], $reading['value']);
                    }
                }

                $monitor->save();
            }
        });
    }

    /**
     * Closes the period the monitor was in, or opens the one it entered.
     *
     * `$breachedAt` is the stamp the transition consumed, which is the reading's
     * own instant when the duration is zero: crossing and confirming are then the
     * same reading, and nothing was stamped before it.
     *
     * The open period is looked up rather than assumed: a monitor whose alarm
     * this class did not record has none, and closing it does nothing.
     */
    private function record(
        AlarmMonitor $monitor,
        AlarmStatus $status,
        CarbonInterface $breachedAt,
        float $value,
    ): void {
        if ($status === AlarmStatus::Alarm) {
            $open = AlarmPeriod::where('alarm_monitor_id', $monitor->id)->whereNull('ended_at')->first();

            if ($open !== null) {
                $open->endAt($monitor->status_since, AlarmEndReason::Transition);
                $open->save();
            }
        }

        if ($monitor->status !== AlarmStatus::Alarm) {
            return;
        }

        $rule = $monitor->rule;

        $period = new AlarmPeriod([
            'sensor_id' => $monitor->sensor_id,
            'alarm_monitor_id' => $monitor->id,
            'breached_at' => $breachedAt,
            'started_at' => $monitor->status_since,
            'value' => $value,
            'type' => $rule->type,
            'label' => $rule->label,
            'direction' => $rule->direction,
            'threshold' => $rule->threshold,
            'trigger_after' => $rule->trigger_after,
            'clear_after' => $rule->clear_after,
        ]);

        // Taken from the rule rather than left to the tenancy trait, so a caller
        // outside an initialized tenancy writes the same row.
        $period->company_id = $rule->company_id;

        $period->save();
    }

    /**
     * The readings of each sensor, oldest first.
     *
     * @param  list<array{sensor_id: int, time: string, value: float}>  $readings
     * @return array<int, list<array{at: CarbonImmutable, value: float}>>
     */
    private function bySensor(array $readings): array
    {
        $bySensor = [];

        foreach ($readings as $reading) {
            $bySensor[$reading['sensor_id']][] = [
                'at' => CarbonImmutable::parse($reading['time']),
                'value' => $reading['value'],
            ];
        }

        return array_map(
            function (array $sensorReadings): array {
                usort($sensorReadings, fn (array $first, array $second): int => $first['at'] <=> $second['at']);

                return $sensorReadings;
            },
            $bySensor,
        );
    }
}
