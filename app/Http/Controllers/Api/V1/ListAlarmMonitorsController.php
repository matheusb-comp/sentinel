<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListAlarmMonitorsRequest;
use App\Http\Resources\Api\V1\AlarmMonitorResource;
use App\Models\AlarmRule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAlarmMonitorsController extends Controller
{
    public function __invoke(ListAlarmMonitorsRequest $request, AlarmRule $alarmRule): AnonymousResourceCollection
    {
        $monitors = $alarmRule->monitors()
            ->with('sensor')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            // So that following a link keeps the page size.
            ->withQueryString();

        return AlarmMonitorResource::collection($monitors);
    }
}
