<?php

use App\Http\Requests\Ingest\V1\SyncSensorsRequest;
use Illuminate\Support\Str;

it('reconciles the sensors with the list the device declares', function () {
    ['device' => $device, 'token' => $token] = registerDevice(['temp', 'hum']);

    $this->withToken($token)->putJson('/in/v1/sync', ['sensors' => [
        ['key' => 'temp', 'description' => 'DS18B20'],
        ['key' => 'co2'],
    ]])->assertNoContent();

    expect($device->sensors()->whereNull('archived_at')->pluck('key')->all())->toEqualCanonicalizing(['temp', 'co2'])
        ->and($device->sensors()->where('key', 'temp')->value('description'))->toBe('DS18B20')
        ->and($device->sensors()->whereNotNull('archived_at')->pluck('key')->all())->toBe(['hum']);
});

it('tells apart keys that are equal only as numbers', function () {
    ['device' => $device, 'token' => $token] = registerDevice();

    $this->withToken($token)->putJson('/in/v1/sync', ['sensors' => [['key' => '1'], ['key' => '01']]])
        ->assertNoContent();

    expect($device->sensors()->whereNull('archived_at')->orderBy('key')->pluck('key')->all())->toBe(['01', '1']);
});

it('refuses a declaration that does not reconcile', function (array $body, string $field) {
    ['device' => $device, 'token' => $token] = registerDevice();

    $this->withToken($token)->putJson('/in/v1/sync', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($device->sensors()->whereNull('archived_at')->pluck('key')->all())->toBe(['temp']);
})->with([
    'missing list' => [[], 'sensors'],
    'empty list' => [['sensors' => []], 'sensors'],
    'not a list' => [['sensors' => ['a' => ['key' => 'temp']]], 'sensors'],
    'missing key' => [['sensors' => [['description' => 'x']]], 'sensors.0.key'],
    'repeated key' => [['sensors' => [['key' => 'temp'], ['key' => 'temp']]], 'sensors.1.key'],
    'key too long' => [['sensors' => [['key' => Str::repeat('k', 256)]]], 'sensors.0.key'],
    'description too long' => [['sensors' => [['key' => 'temp', 'description' => Str::repeat('d', 256)]]], 'sensors.0.description'],
    'over the maximum' => [
        ['sensors' => array_map(fn (int $i): array => ['key' => "s{$i}"], range(0, SyncSensorsRequest::MAX_SENSORS))],
        'sensors',
    ],
]);

it('refuses a body sent without a JSON content type', function () {
    ['token' => $token] = registerDevice();

    $this->withToken($token)->put('/in/v1/sync', ['sensors' => [['key' => 'temp']]])
        ->assertStatus(415);
});

it('refuses a body that is not valid JSON', function () {
    ['token' => $token] = registerDevice();

    $this->call('PUT', '/in/v1/sync', server: $this->transformHeadersToServerVars([
        'Authorization' => "Bearer {$token}",
        'Content-Type' => 'application/json',
    ]), content: '{"sensors": [')->assertStatus(400);
});

it('answers a validation error as JSON to a request that does not ask for JSON', function () {
    ['token' => $token] = registerDevice();

    $this->call('PUT', '/in/v1/sync', server: $this->transformHeadersToServerVars([
        'Authorization' => "Bearer {$token}",
        'Content-Type' => 'application/json',
    ]), content: '{"sensors": []}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sensors');
});
