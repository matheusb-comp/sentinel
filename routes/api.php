<?php

use App\Http\Controllers\Api\V1\ArchiveDeviceController;
use App\Http\Controllers\Api\V1\CreateAlarmRuleController;
use App\Http\Controllers\Api\V1\DeleteAlarmRuleController;
use App\Http\Controllers\Api\V1\ListAlarmMonitorsController;
use App\Http\Controllers\Api\V1\ListAlarmRulesController;
use App\Http\Controllers\Api\V1\ListDevicesController;
use App\Http\Controllers\Api\V1\ListDeviceTokensController;
use App\Http\Controllers\Api\V1\RegisterDeviceController;
use App\Http\Controllers\Api\V1\RevokeDeviceTokenController;
use App\Http\Controllers\Api\V1\ShowCompanyController;
use App\Http\Controllers\Api\V1\ShowDeviceController;
use App\Http\Controllers\Api\V1\ShowMembershipController;
use App\Http\Controllers\Api\V1\UnwatchSensorsController;
use App\Http\Controllers\Api\V1\WatchSensorsController;
use App\Http\Middleware\ResolveCompanyByUuid;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

// Execution order comes from middleware priority in TenancyServiceProvider.
Route::prefix('v1/companies/{company}')
    ->middleware([
        'auth:sanctum',
        'verified',
        ResolveCompanyByUuid::class,
        InitializeTenancyByPath::class,
        'tenant.member',
    ])
    ->group(function () {
        Route::get('/', ShowCompanyController::class);
        Route::get('/membership', ShowMembershipController::class);

        Route::get('/devices', ListDevicesController::class);
        Route::post('/devices', RegisterDeviceController::class);
        Route::get('/devices/{device}', ShowDeviceController::class);
        Route::delete('/devices/{device}', ArchiveDeviceController::class);

        Route::get('/alarm-rules', ListAlarmRulesController::class);
        Route::post('/alarm-rules', CreateAlarmRuleController::class);
        Route::delete('/alarm-rules/{alarmRule}', DeleteAlarmRuleController::class);

        Route::get('/alarm-rules/{alarmRule}/monitors', ListAlarmMonitorsController::class);
        Route::post('/alarm-rules/{alarmRule}/monitors', WatchSensorsController::class);
        Route::delete('/alarm-rules/{alarmRule}/monitors', UnwatchSensorsController::class);

        Route::get('/devices/{device}/tokens', ListDeviceTokensController::class);
        Route::delete('/devices/{device}/tokens/{token}', RevokeDeviceTokenController::class)->scopeBindings();
    });
