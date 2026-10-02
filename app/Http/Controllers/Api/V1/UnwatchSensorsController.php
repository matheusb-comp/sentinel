<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Alarms\CloseAlarmPeriods;
use App\Alarms\AlarmEndReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UnwatchSensorsRequest;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use App\Models\Sensor;
use Illuminate\Http\Response;

class UnwatchSensorsController extends Controller
{
    public function __invoke(
        UnwatchSensorsRequest $request,
        AlarmRule $alarmRule,
        CloseAlarmPeriods $closeAlarmPeriods,
    ): Response {
        $alarmRule->getConnection()->transaction(function () use ($request, $alarmRule, $closeAlarmPeriods): void {
            // Only the monitors of this rule, so a sensor another rule watches, or
            // a uuid that names nothing, is left alone.
            $monitors = $alarmRule->monitors()
                ->whereIn('sensor_id', Sensor::whereIn('uuid', $request->validated('sensor_uuids'))->select('id'))
                ->pluck('id')
                ->all();

            $closeAlarmPeriods->handle($monitors, AlarmEndReason::Unwatched);

            AlarmMonitor::whereIn('id', $monitors)->delete();
        });

        return response()->noContent();
    }
}
