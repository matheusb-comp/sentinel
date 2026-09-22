<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Devices\SyncDeviceSensors;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateDevice;
use App\Http\Requests\Api\V1\SyncSensorsRequest;
use Illuminate\Http\Response;

class SyncSensorsController extends Controller
{
    public function __invoke(SyncSensorsRequest $request, SyncDeviceSensors $sync): Response
    {
        $sync->handle($request->attributes->get(AuthenticateDevice::ATTRIBUTE), $request->validated('sensors'));

        return response()->noContent();
    }
}
