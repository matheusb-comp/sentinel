<?php

use App\Http\Middleware\EnsureMembership;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.member' => EnsureMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Avoid proving the company existence by returning 404 instead of 500.
        $exceptions->map(
            fn (TenantCouldNotBeIdentifiedException $e) => new NotFoundHttpException(previous: $e),
        );

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
