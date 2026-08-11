<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Bootstrappers\PostgresRLSBootstrapper;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        //  RLS not available on SQLite, so Tenant scope is application-level only.
        config()->set('tenancy.bootstrappers', array_values(array_filter(
            config('tenancy.bootstrappers'),
            fn (string $bootstrapper): bool => $bootstrapper !== PostgresRLSBootstrapper::class,
        )));
    })
    ->in('Feature');

/**
 * DatabaseTruncation rather than RefreshDatabase.
 *
 * The PostgresRLSBootstrapper reconnects as a different Postgres user, which is
 * a different connection. RefreshDatabase keeps every test inside an uncommitted
 * transaction on the central connection, so the tenant connection would see an
 * empty database. Truncation commits, so both connections agree.
 */
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(function () {
        // RLS policies must be reset after `migrate:fresh` on DatabaseTruncation.
        $this->artisan('tenants:rls');
    })
    ->in('Tenancy');
