<?php

use App\Actions\CreateDevice;
use App\Models\Company;
use App\Models\Device;
use Illuminate\Database\QueryException;

it('creates the device with the sensors it declared', function () {
    $company = Company::factory()->create();

    $device = app(CreateDevice::class)->handle($company, 'ATIVO-1', [
        ['key' => 'temp', 'description' => 'DS18B20 3-pin 1 meter cable'],
        ['key' => 'hum', 'description' => null],
    ], 'Câmara fria 3');

    expect($device->company_id)->toBe($company->id)
        ->and($device->label)->toBe('Câmara fria 3')
        ->and($device->sensors()->pluck('key')->all())->toEqualCanonicalizing(['temp', 'hum'])
        ->and($device->sensors()->where('key', 'temp')->value('description'))
        ->toBe('DS18B20 3-pin 1 meter cable');
});

it('returns the device already registered under the key instead of a second one', function () {
    $company = Company::factory()->create();
    $action = app(CreateDevice::class);

    $first = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);
    $second = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

    expect($second->id)->toBe($first->id)
        ->and(Device::count())->toBe(1);
});

it('leaves the label and the sensors of a registered device untouched', function () {
    $company = Company::factory()->create();
    $action = app(CreateDevice::class);

    $device = $action->handle($company, 'ATIVO-1', [
        ['key' => 'temp', 'description' => 'original'],
    ], 'Named by a person');

    $action->handle($company, 'ATIVO-1', [
        ['key' => 'hum', 'description' => 'declared later'],
    ], 'Renamed by a script');

    expect($device->fresh()->label)->toBe('Named by a person')
        ->and($device->sensors()->pluck('key')->all())->toBe(['temp']);
});

it('reactivates an archived device registered under the same key', function () {
    $company = Company::factory()->create();
    $action = app(CreateDevice::class);

    $device = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);
    $device->archived_at = now();
    $device->save();

    $again = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

    expect($again->id)->toBe($device->id)
        ->and($again->archived_at)->toBeNull()
        ->and(Device::count())->toBe(1);
});

it('creates no device when one of the sensors is rejected', function () {
    $company = Company::factory()->create();

    $create = fn () => app(CreateDevice::class)->handle($company, 'ATIVO-1', [
        ['key' => 'temp', 'description' => null],
        ['key' => 'temp', 'description' => null],
    ]);

    expect($create)->toThrow(QueryException::class)
        ->and(Device::count())->toBe(0);
});
