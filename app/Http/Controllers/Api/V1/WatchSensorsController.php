<?php

namespace App\Http\Controllers\Api\V1;

use App\Alarms\AlarmStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WatchSensorsRequest;
use App\Http\Resources\Api\V1\AlarmMonitorResource;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WatchSensorsController extends Controller
{
    public function __invoke(WatchSensorsRequest $request, AlarmRule $alarmRule): JsonResponse
    {
        $sensors = Sensor::whereIn('uuid', $request->validated('sensor_uuids'))->get();

        // One transaction, so a list that fails halfway leaves no partial set of
        // watches behind the error.
        $monitors = DB::transaction(fn () => $sensors->map(fn (Sensor $sensor): AlarmMonitor => $alarmRule->monitors()
            // A sensor the rule already watches keeps the watch it has, with the
            // state it has reached.
            ->firstOrCreate(
                ['sensor_id' => $sensor->id],
                ['status' => AlarmStatus::Waiting, 'status_since' => now()],
            )
            ->setRelation('sensor', $sensor)));

        // Only what this request started watching. What the rule already covered
        // is answered by the listing.
        return AlarmMonitorResource::collection(
            $monitors->filter(fn (AlarmMonitor $monitor): bool => $monitor->wasRecentlyCreated)->values(),
        )->response();
    }
}
