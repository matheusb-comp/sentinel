<?php

use App\Http\Middleware\EnsureMembership;
use App\Http\Middleware\RejectMalformedJson;
use App\Http\Middleware\RemoveNullBytes;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->prefix('in')->group(__DIR__.'/../routes/ingest.php');
        },
    )
    ->withEvents()
    ->withMiddleware(function (Middleware $middleware): void {
        // Before TrimStrings, so credentials it exempts are not rewritten here,
        // and before ConvertEmptyStringsToNull, so a value left empty converts.
        $middleware->prepend(RemoveNullBytes::class);

        $middleware->append(RejectMalformedJson::class);

        $middleware->alias([
            'tenant.member' => EnsureMembership::class,
        ]);

        // No login page: guests get 401 instead of a redirect.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Avoid proving the company existence by returning 404 instead of 500.
        $exceptions->map(
            fn (TenantCouldNotBeIdentifiedException $e) => new NotFoundHttpException(previous: $e),
        );

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*', 'in/*'),
        );
    })->create();
