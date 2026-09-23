<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Devices\CreateDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Http\Resources\Api\V1\DeviceResource;
use Illuminate\Http\JsonResponse;

class RegisterDeviceController extends Controller
{
    public function __invoke(RegisterDeviceRequest $request, CreateDevice $register): JsonResponse
    {
        ['device' => $device, 'token' => $token] = $register->handle(
            $request->validated('key'),
            $request->validated('sensors'),
            $request->validated('label'),
        );

        // Response includes the clear text token, it WILL NOT be stored.
        return (new DeviceResource($device))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode($device->wasRecentlyCreated ? 201 : 200);
    }
}
