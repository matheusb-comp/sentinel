<?php

use App\Actions\Devices\ArchiveDevice;
use App\Actions\Devices\CreateDevice;
use App\Models\Company;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('creates the device with the sensors it declared', function () {
    $company = Company::factory()->create();

    ['device' => $device] = app(CreateDevice::class)->handle($company, 'ATIVO-1', [
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

    expect($second['device']->id)->toBe($first['device']->id)
        ->and(Device::count())->toBe(1);
});

it('leaves the label and the sensors of a registered device untouched', function () {
    $company = Company::factory()->create();
    $action = app(CreateDevice::class);

    ['device' => $device] = $action->handle($company, 'ATIVO-1', [
        ['key' => 'temp', 'description' => 'original'],
    ], 'Named by a person');

    $action->handle($company, 'ATIVO-1', [
        ['key' => 'hum', 'description' => 'declared later'],
    ], 'Renamed by a script');

    expect($device->fresh()->label)->toBe('Named by a person')
        ->and($device->sensors()->pluck('key')->all())->toBe(['temp']);
});

it('reactivates an archived device registered under the same key, with a new token', function () {
    $company = Company::factory()->create();
    $action = app(CreateDevice::class);

    ['device' => $device] = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);
    app(ArchiveDevice::class)->handle($device);

    $again = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

    expect($again['device']->id)->toBe($device->id)
        ->and($again['device']->archived_at)->toBeNull()
        ->and(Device::count())->toBe(1)
        ->and(PersonalAccessToken::findToken($again['token'])->tokenable->is($device))->toBeTrue()
        ->and($device->tokens()->count())->toBe(1);
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

it('issues a token that finds the device', function () {
    ['device' => $device, 'token' => $token] = registerDevice();

    expect($token)->not->toContain('|')
        ->and(PersonalAccessToken::findToken($token)->tokenable->is($device))->toBeTrue()
        ->and(PersonalAccessToken::sole()->tokenable_type)->toBe('device')
        ->and(PersonalAccessToken::sole()->company_id)->toBe($device->company_id);
});

it('finds the tokens of devices loaded together', function () {
    ['device' => $device] = registerDevice();

    expect(Device::withCount('tokens')->find($device->id)->tokens_count)->toBe(1)
        ->and(Device::with('tokens')->find($device->id)->tokens)->toHaveCount(1);
});

it('issues a new token on every registration and keeps the earlier ones', function () {
    $company = Company::factory()->create();
    $action = app(CreateDevice::class);

    $first = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);
    $second = $action->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

    expect($second['token'])->not->toBe($first['token'])
        ->and(PersonalAccessToken::findToken($first['token']))->not->toBeNull()
        ->and($first['device']->tokens()->count())->toBe(2);
});

it('refuses to register a device that holds the maximum number of tokens', function () {
    config(['ingestion.max_tokens_per_device' => 2]);
    $company = Company::factory()->create();
    $register = fn () => app(CreateDevice::class)->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

    $register();
    $register();

    expect($register)->toThrow(ValidationException::class)
        ->and(PersonalAccessToken::count())->toBe(2);
});
