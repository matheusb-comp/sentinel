<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UnwatchSensorsRequest;
use App\Models\AlarmRule;
use App\Models\Sensor;
use Illuminate\Http\Response;

class UnwatchSensorsController extends Controller
{
    public function __invoke(UnwatchSensorsRequest $request, AlarmRule $alarmRule): Response
    {
        // Only the monitors of this rule, so a sensor another rule watches, or a
        // uuid that names nothing, is left alone.
        $alarmRule->monitors()
            ->whereIn('sensor_id', Sensor::whereIn('uuid', $request->validated('sensor_uuids'))->select('id'))
            ->delete();

        return response()->noContent();
    }
}
