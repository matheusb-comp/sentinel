<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sqids\Sqids;

class SqidsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Sqids::class, function () {
            $minLength = 8;
            $alphabet = 'zwdkfr38x6p2ab5se4yvtq1jh79cgmn0u';
            return new Sqids($alphabet, $minLength);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
