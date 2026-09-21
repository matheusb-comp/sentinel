<?php

use App\Actions\CreateCompanyForUser;
use App\Database\Partitioning\PartitionInterval;
use App\Database\Partitioning\PartitionMaintainer;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement(<<<'SQL'
        CREATE TABLE probe_sensors (
            id bigserial PRIMARY KEY,
            company_id bigint NOT NULL REFERENCES companies (id)
        )
    SQL);

    DB::statement(<<<'SQL'
        CREATE TABLE probe_readings (
            time timestamptz(6) NOT NULL,
            sensor_id bigint NOT NULL REFERENCES probe_sensors (id),
            value double precision NOT NULL,
            PRIMARY KEY (sensor_id, time)
        ) PARTITION BY RANGE (time)
    SQL);
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS probe_readings CASCADE');
    DB::statement('DROP TABLE IF EXISTS probe_sensors CASCADE');
});

/**
 * Two companies, one sensor each, one reading each, and policies in place.
 *
 * @return array{0: Company, 1: Company}
 */
function seedProbeReadings(): array
{
    $action = app(CreateCompanyForUser::class);
    $companyA = $action->handle(User::factory()->create(), 'A');
    $companyB = $action->handle(User::factory()->create(), 'B');

    app(PartitionMaintainer::class)->maintain('probe_readings', PartitionInterval::Day, premake: 0);

    foreach ([$companyA, $companyB] as $company) {
        $sensorId = DB::table('probe_sensors')->insertGetId(['company_id' => $company->id]);

        DB::table('probe_readings')->insert([
            'time' => CarbonImmutable::now('UTC')->toDateTimeString(),
            'sensor_id' => $sensorId,
            'value' => 22.4,
        ]);
    }

    Artisan::call('tenants:rls');

    return [$companyA, $companyB];
}

it('generates policies for a partitioned table without failing', function () {
    seedProbeReadings();

    $policies = DB::table('pg_policies')->where('tablename', 'probe_readings')->pluck('policyname');

    expect($policies)->not->toBeEmpty();
});

it('scopes a partitioned table to the tenant when queried through the parent', function () {
    [$companyA] = seedProbeReadings();
    $sensorOfA = DB::table('probe_sensors')->where('company_id', $companyA->id)->value('id');

    tenancy()->initialize($companyA);
    $visible = DB::table('probe_readings')->pluck('sensor_id')->all();
    tenancy()->end();

    expect($visible)->toBe([$sensorOfA]);
});

it('scopes direct access to a partition that existed when the policies were generated', function () {
    [$companyA] = seedProbeReadings();
    $sensorOfA = DB::table('probe_sensors')->where('company_id', $companyA->id)->value('id');
    $partition = probePartitions()[0];

    tenancy()->initialize($companyA);
    $visible = DB::table($partition)->pluck('sensor_id')->all();
    tenancy()->end();

    expect($visible)->toBe([$sensorOfA]);
});

it('denies direct access to a partition created after the policies were generated', function () {
    [$companyA] = seedProbeReadings();

    $this->travelTo(CarbonImmutable::now('UTC')->addDay());
    $created = app(PartitionMaintainer::class)
        ->maintain('probe_readings', PartitionInterval::Day, premake: 0)
        ->created;

    expect($created)->toHaveCount(1);

    tenancy()->initialize($companyA);
    $read = fn () => DB::table($created[0])->count();
    expect($read)->toThrow(QueryException::class, 'permission denied');
    tenancy()->end();
});
