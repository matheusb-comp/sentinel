<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeviceTokenResource;
use App\Models\Device;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListDeviceTokensController extends Controller
{
    public function __invoke(Device $device): AnonymousResourceCollection
    {
        return DeviceTokenResource::collection($device->tokens()->orderByDesc('id')->get());
    }
}
