<?php

namespace App\Actions\Alarms;

use App\Alarms\AlarmType;
use App\Models\AlarmMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Moves every monitor the readings of one batch touch.
 *
 * The readings of a sensor are walked in order of their own instant, inside a
 * transaction, because the lock it takes on the monitors is what makes two
 * batches of the same sensor run one after the other instead of interleaved.
 *
 * Two reads, whatever the size of the batch. A monitor that did not move is not
 * dirty and writes nothing.
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
                ->with('rule')
                ->lockForUpdate()
                ->get();

            $now = CarbonImmutable::now('UTC');

            foreach ($monitors as $monitor) {
                foreach ($bySensor[$monitor->sensor_id] as $reading) {
                    $this->evaluate->handle($monitor->rule, $monitor, $reading['at'], $reading['value'], $now);
                }

                $monitor->save();
            }
        });
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
