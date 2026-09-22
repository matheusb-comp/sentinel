<?php

namespace App\Http\Controllers\Ingest\V1;

use App\Actions\Readings\IngestReadings;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateDevice;
use App\Http\Requests\Ingest\V1\IngestReadingsRequest;
use Illuminate\Http\JsonResponse;

class IngestReadingsController extends Controller
{
    public function __invoke(IngestReadingsRequest $request, IngestReadings $ingest): JsonResponse
    {
        return response()->json($ingest->handle(
            $request->attributes->get(AuthenticateDevice::ATTRIBUTE),
            $request->validated('readings'),
        ));
    }
}
