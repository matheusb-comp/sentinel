<?php

use App\Http\Middleware\ResolveCompanyByUuid;
use App\Models\CompanyUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Bootstrappers\PostgresRLSBootstrapper;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
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
    ->in('Postgres');

/**
 * Registers a tenant route that binds a membership by its uuid, with the same
 * middleware as the tenant routes in routes/web.php.
 */
function registerMembershipRoute(): void
{
    Route::middleware(['web', 'auth', 'verified', ResolveCompanyByUuid::class, InitializeTenancyByPath::class, 'tenant.member'])
        ->get('/companies/{company}/members/{member}', fn (CompanyUser $member) => ['member_uuid' => $member->uuid]);
}

/**
 * The partitions of the probe_readings fixture table, by name, in order.
 *
 * @return list<string>
 */
function probePartitions(): array
{
    return DB::table('pg_inherits')
        ->join('pg_class as parent', 'parent.oid', '=', 'pg_inherits.inhparent')
        ->join('pg_class as child', 'child.oid', '=', 'pg_inherits.inhrelid')
        ->where('parent.relname', 'probe_readings')
        ->orderBy('child.relname')
        ->pluck('child.relname')
        ->all();
}

/**
 * Every model class in app/Models.
 *
 * @return list<class-string<Model>>
 */
function modelClasses(): array
{
    return collect(File::files(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->all();
}
