<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Devices\ArchiveDevice;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Response;

class ArchiveDeviceController extends Controller
{
    public function __invoke(Device $device, ArchiveDevice $archive): Response
    {
        $archive->handle($device);

        return response()->noContent();
    }
}
