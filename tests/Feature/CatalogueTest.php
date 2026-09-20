<?php

use App\Actions\CreateCompanyForUser;
use App\Models\Company;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('gives a device and a sensor a uuid version 7', function () {
    $sensor = Sensor::factory()->create();

    expect(Str::isUuid($sensor->uuid, 7))->toBeTrue()
        ->and(Str::isUuid($sensor->device->uuid, 7))->toBeTrue();
});

it('hides the numeric keys from serialization', function () {
    $sensor = Sensor::factory()->create();

    expect($sensor->toArray())->not->toHaveKeys(['id', 'device_id'])
        ->and($sensor->device->toArray())->not->toHaveKeys(['id', 'company_id']);
});

it('keeps sensor keys unique within a device and free between devices', function () {
    $device = Device::factory()->create();
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp']);
    Sensor::factory()->create(['key' => 'temp']);

    $duplicate = fn () => Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp']);

    expect($duplicate)->toThrow(QueryException::class);
});

it('treats keys that differ only in case as different sensors', function () {
    $device = Device::factory()->create();

    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp']);
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'Temp']);

    expect($device->sensors()->count())->toBe(2);
});

it('keeps device keys unique within a company and free between companies', function () {
    $company = Company::factory()->create();
    Device::factory()->create(['company_id' => $company->id, 'key' => 'ATIVO-1']);
    Device::factory()->create(['key' => 'ATIVO-1']);

    $duplicate = fn () => Device::factory()->create(['company_id' => $company->id, 'key' => 'ATIVO-1']);

    expect($duplicate)->toThrow(QueryException::class);
});

it('builds a device inside the current tenant instead of a new company', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');

    tenancy()->initialize($company);
    $device = Device::factory()->create();
    tenancy()->end();

    expect($device->company_id)->toBe($company->id);
});

it('hides sensors of another company while tenancy is initialized', function () {
    $action = app(CreateCompanyForUser::class);
    $companyA = $action->handle(User::factory()->create(), 'A');
    $companyB = $action->handle(User::factory()->create(), 'B');

    $mine = Sensor::factory()->create([
        'device_id' => Device::factory()->create(['company_id' => $companyA->id]),
    ]);
    Sensor::factory()->create([
        'device_id' => Device::factory()->create(['company_id' => $companyB->id]),
    ]);

    tenancy()->initialize($companyA);
    $visible = Sensor::pluck('uuid')->all();
    tenancy()->end();

    expect($visible)->toBe([$mine->uuid]);
});
