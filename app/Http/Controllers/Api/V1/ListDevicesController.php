<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDevicesRequest;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListDevicesController extends Controller
{
    public function __invoke(ListDevicesRequest $request): AnonymousResourceCollection
    {
        $devices = Device::when(
            // Absent, the filter takes both the archived and the active ones.
            $request->filled('archived'),
            fn (Builder $query) => $query->whereNull('archived_at', not: $request->boolean('archived')),
        )
            ->activeFirst()
            ->orderBy('key')
            ->paginate($request->perPage())
            // So that following a link keeps the filter and the page size.
            ->withQueryString();

        return DeviceResource::collection($devices);
    }
}
