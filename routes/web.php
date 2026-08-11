<?php

use App\Models\CompanyUser;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::get('/', function () {
    return view('welcome');
});

// Order matters: the tenant is identified from the path first, and only then
// is the authenticated user authorized against it.
Route::middleware(['auth', InitializeTenancyByPath::class, 'tenant.member'])
    ->prefix('companies/{company}')
    ->group(function () {
        // Exercises the middleware chain for this cycle. It goes away once
        // there is real CRUD to serve.
        Route::get('/ping', function () {
            return response()->json([
                'company' => tenant()->slug,
                'membership' => app(CompanyUser::class)->id,
            ]);
        });
    });
