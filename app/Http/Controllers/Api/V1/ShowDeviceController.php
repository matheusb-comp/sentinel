<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\Device;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShowDeviceController extends Controller
{
    public function __invoke(Device $device): DeviceResource
    {
        $device->load(['sensors' => fn (HasMany $sensors) => $sensors->activeFirst()->orderBy('key')]);

        return new DeviceResource($device);
    }
}
