<?php

use App\Http\Controllers\Ingest\V1\IngestReadingsController;
use App\Http\Controllers\Ingest\V1\SyncSensorsController;
use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\RequireJsonBody;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([AuthenticateDevice::class, RequireJsonBody::class])->group(function () {
    Route::post('/data', IngestReadingsController::class);
    Route::put('/sync', SyncSensorsController::class);
});
