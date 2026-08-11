<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Actions\CloneRoutesAsTenant;
use Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * Tenancy for Laravel.
 *
 * Documentation: https://tenancyforlaravel.com
 *
 * We can sustainably develop Tenancy for Laravel thanks to our sponsors.
 * Big thanks to everyone listed here: https://github.com/sponsors/stancl
 *
 * You can also support us, and save time, by purchasing these products:
 *   Exclusive content for sponsors: https://sponsors.tenancyforlaravel.com
 *   Multi-Tenant SaaS boilerplate: https://portal.archte.ch/boilerplate
 *   Multi-Tenant Laravel in Production e-book: https://portal.archte.ch/book
 *
 * All of these products can also be accessed at https://portal.archte.ch
 */
class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    /**
     * Tenancy events and their listeners.
     *
     * Database lifecycle events are intentionally absent: this application is
     * single-database and no database is created per tenant. Domain, pending
     * tenant, resource syncing and storage symlink events are absent for the
     * same reason — none of those features are in use.
     *
     * @return array<class-string, array<int, mixed>>
     */
    public function events()
    {
        return [
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
        ];
    }

    /**
     * Set \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper::$rootUrlOverride here
     * to override the root URL used in CLI while in tenant context.
     *
     * @see RootUrlBootstrapper
     */
    protected function overrideUrlInTenantContext(): void
    {
        // \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper::$rootUrlOverride = function (Tenant $tenant, string $originalRootUrl) {
        //     $tenantDomain = $tenant instanceof \Stancl\Tenancy\Contracts\SingleDomainTenant
        //         ? $tenant->domain
        //         : $tenant->domains->first()->domain;
        //
        //     if (is_null($tenantDomain)) {
        //         return $originalRootUrl;
        //     }
        //
        //     $scheme = str($originalRootUrl)->before('://');
        //
        //     if (str_contains($tenantDomain, '.')) {
        //         // Domain identification
        //         return $scheme . '://' . $tenantDomain . '/';
        //     } else {
        //         // Subdomain identification
        //         $originalDomain = str($originalRootUrl)->after($scheme . '://')->before('/');
        //         return $scheme . '://' . $tenantDomain . '.' . $originalDomain . '/';
        //     }
        // };
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->overrideUrlInTenantContext();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->middleware('tenant')
                    ->group(base_path('routes/tenant.php'));
            }

            // $this->cloneRoutes();
        });
    }

    /**
     * Clone routes as tenant.
     *
     * This is used primarily for integrating packages.
     *
     * @see CloneRoutesAsTenant
     */
    protected function cloneRoutes(): void
    {
        /** @var CloneRoutesAsTenant $cloneRoutes */
        $cloneRoutes = $this->app->make(CloneRoutesAsTenant::class);

        /** See CloneRoutesAsTenant for usage details. */
        $cloneRoutes->handle();
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        // PreventAccessFromUnwantedDomains has even higher priority than the identification middleware
        $tenancyMiddleware = array_merge([Middleware\PreventAccessFromUnwantedDomains::class], config('tenancy.identification.middleware'));

        // Resolved through the contract because that is what the container binds,
        // but annotated as the concrete kernel: prependToMiddlewarePriority is
        // declared there, not on Contracts\Http\Kernel.
        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(Kernel::class);

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
