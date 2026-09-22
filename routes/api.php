<?php

use App\Http\Controllers\Api\V1\IngestReadingsController;
use App\Http\Controllers\Api\V1\SyncSensorsController;
use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\RequireJsonBody;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([AuthenticateDevice::class, RequireJsonBody::class])->group(function () {
    Route::post('/readings', IngestReadingsController::class);
    Route::put('/sensors', SyncSensorsController::class);
});
