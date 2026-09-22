<?php

use App\Http\Controllers\Api\V1\ShowCompanyController;
use App\Http\Controllers\Api\V1\ShowMembershipController;
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
    });
