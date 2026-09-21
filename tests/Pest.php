<?php

use App\Actions\CreateCompanyForUser;
use App\Database\Partitioning\PartitionInterval;
use App\Database\Partitioning\PartitionMaintainer;
use App\Http\Middleware\ResolveCompanyByUuid;
use App\Models\CompanyUser;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
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
        // migrate:fresh leaves the series tables out and drops the policies,
        // which are derived from the tables that exist, hence this order.
        // Partitions come from seedSeriesSensor(), in the tests that write.
        $this->artisan('series:setup');
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

/**
 * A sensor of a company of its own, with the current partition of every series
 * table already created.
 */
function seedSeriesSensor(): Sensor
{
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');
    $device = Device::factory()->create(['company_id' => $company->id]);

    foreach (config('series.tables') as $table => $settings) {
        app(PartitionMaintainer::class)->maintain(
            $table,
            PartitionInterval::from($settings['partition']),
            premake: 0,
        );
    }

    return Sensor::factory()->create(['device_id' => $device->id]);
}
