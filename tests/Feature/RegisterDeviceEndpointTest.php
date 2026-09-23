<?php

use App\Actions\Devices\ArchiveDevice;
use App\Models\Device;
use App\Models\PersonalAccessToken;

it('registers a device and answers with its token', function () {
    $company = actingAsMember();

    $response = $this->postJson("/api/v1/companies/{$company->slug}/devices", [
        'key' => 'ATIVO-1',
        'label' => 'Câmara fria 3',
        'sensors' => [['key' => 'temp', 'description' => 'DS18B20']],
    ])->assertCreated();

    $device = Device::sole();

    expect($response->json('data'))->toBe([
        'uuid' => $device->uuid,
        'key' => 'ATIVO-1',
        'label' => 'Câmara fria 3',
        'archived_at' => null,
    ])
        ->and($device->company_id)->toBe($company->id)
        ->and($device->sensors()->pluck('key')->all())->toBe(['temp'])
        ->and(PersonalAccessToken::findToken($response->json('token'))->tokenable->is($device))->toBeTrue();
});

it('answers 200 when the key is already registered, with another token', function () {
    $company = actingAsMember();
    ['device' => $device, 'token' => $first] = registerDeviceIn($company, 'ATIVO-1');

    $response = $this->postJson("/api/v1/companies/{$company->slug}/devices", ['key' => 'ATIVO-1'])
        ->assertOk();

    expect($response->json('data.uuid'))->toBe($device->uuid)
        ->and($response->json('token'))->not->toBe($first)
        ->and(PersonalAccessToken::findToken($first))->not->toBeNull()
        ->and(Device::count())->toBe(1);
});

it('reactivates an archived device', function () {
    $company = actingAsMember();
    ['device' => $device] = registerDeviceIn($company, 'ATIVO-1');
    app(ArchiveDevice::class)->handle($device);

    $this->postJson("/api/v1/companies/{$company->slug}/devices", [
        'key' => 'ATIVO-1',
        'sensors' => [['key' => 'temp', 'description' => null]],
    ])->assertOk();

    expect($device->fresh()->archived_at)->toBeNull()
        ->and($device->sensors()->sole()->archived_at)->toBeNull();
});

it('reconciles the sensors the body declares', function () {
    $company = actingAsMember();
    ['device' => $device] = registerDeviceIn($company, 'ATIVO-1', ['temp']);

    $this->postJson("/api/v1/companies/{$company->slug}/devices", [
        'key' => 'ATIVO-1',
        'sensors' => [['key' => 'hum', 'description' => null]],
    ])->assertOk();

    expect($device->sensors()->whereNull('archived_at')->pluck('key')->all())->toBe(['hum'])
        ->and($device->sensors()->whereNotNull('archived_at')->pluck('key')->all())->toBe(['temp']);
});

it('leaves the sensors and the label alone when the body omits them', function () {
    $company = actingAsMember();
    ['device' => $device] = registerDeviceIn($company, 'ATIVO-1', ['temp']);
    $device->update(['label' => 'Named by a person']);

    $this->postJson("/api/v1/companies/{$company->slug}/devices", ['key' => 'ATIVO-1'])
        ->assertOk();

    expect($device->fresh()->label)->toBe('Named by a person')
        ->and($device->sensors()->whereNull('archived_at')->pluck('key')->all())->toBe(['temp']);
});

it('refuses a registration without a key', function () {
    $company = actingAsMember();

    $this->postJson("/api/v1/companies/{$company->slug}/devices", ['label' => 'No key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('key');
});

it('refuses an empty sensor list', function () {
    $company = actingAsMember();

    $this->postJson("/api/v1/companies/{$company->slug}/devices", ['key' => 'ATIVO-1', 'sensors' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sensors');
});

it('refuses a repeated sensor key', function () {
    $company = actingAsMember();

    $this->postJson("/api/v1/companies/{$company->slug}/devices", [
        'key' => 'ATIVO-1',
        'sensors' => [['key' => 'temp'], ['key' => 'temp']],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sensors.1.key');
});

it('refuses more sensors than a device may declare', function () {
    config(['ingestion.max_sensors_per_device' => 2]);
    $company = actingAsMember();

    $this->postJson("/api/v1/companies/{$company->slug}/devices", [
        'key' => 'ATIVO-1',
        'sensors' => array_map(fn (int $i): array => ['key' => "s{$i}"], range(1, 3)),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sensors');
});

it('refuses a device that holds the maximum number of tokens', function () {
    config(['ingestion.max_tokens_per_device' => 1]);
    $company = actingAsMember();
    registerDeviceIn($company, 'ATIVO-1');

    $this->postJson("/api/v1/companies/{$company->slug}/devices", ['key' => 'ATIVO-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('key');
});

it('registers a key another company already uses', function () {
    $company = actingAsMember();
    ['device' => $foreign] = registerDevice();

    $this->postJson("/api/v1/companies/{$company->slug}/devices", ['key' => $foreign->key])
        ->assertCreated();

    expect(Device::where('key', $foreign->key)->count())->toBe(2);
});
