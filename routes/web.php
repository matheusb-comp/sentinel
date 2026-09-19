<?php

use App\Http\Middleware\EnsureMembership;
use App\Http\Middleware\ResolveCompanyByUuid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::get('/', function () {
    return view('welcome');
});

// Redirect target of EnsureEmailIsVerified for non-JSON requests.
Route::get('/email/verify', function () {
    return response()->json(['message' => 'Your email address is not verified.'], 403);
})->middleware('auth')->name('verification.notice');

// Execution order comes from middleware priority in TenancyServiceProvider.
Route::middleware(['auth', 'verified', ResolveCompanyByUuid::class, InitializeTenancyByPath::class, 'tenant.member'])
    ->prefix('companies/{company}')
    ->group(function () {
        // Placeholder endpoint for the tenant middleware chain.
        Route::get('/ping', function (Request $request) {
            return response()->json([
                'company' => tenant()->slug,
                'membership_uuid' => $request->attributes->get(EnsureMembership::ATTRIBUTE)->uuid,
            ]);
        });
    });
