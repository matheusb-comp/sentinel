<?php

use App\Models\PersonalAccessToken;

it('lists the tokens of the device without their text', function () {
    $company = actingAsMember();
    ['device' => $device, 'token' => $token] = registerDeviceIn($company, 'ATIVO-1');
    $issued = $device->tokens()->sole();

    $response = $this->getJson("/api/v1/companies/{$company->slug}/devices/{$device->uuid}/tokens")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $issued->uuid)
        ->assertJsonPath('data.0.last_used_at', null);

    expect($response->content())->not->toContain($token);
});

it('does not list the tokens of a device of another company', function () {
    $company = actingAsMember();
    ['device' => $foreign] = registerDevice();

    $this->getJson("/api/v1/companies/{$company->slug}/devices/{$foreign->uuid}/tokens")
        ->assertNotFound();
});

it('revokes a token of the device', function () {
    $company = actingAsMember();
    ['device' => $device, 'token' => $token] = registerDeviceIn($company, 'ATIVO-1');
    $issued = $device->tokens()->sole();

    $this->deleteJson("/api/v1/companies/{$company->slug}/devices/{$device->uuid}/tokens/{$issued->uuid}")
        ->assertNoContent();

    expect(PersonalAccessToken::findToken($token))->toBeNull();

    $this->withToken($token)
        ->putJson('/in/v1/sync', ['sensors' => [['key' => 'temp']]])
        ->assertUnauthorized();
});

it('does not revoke a token of another device', function () {
    $company = actingAsMember();
    ['device' => $device] = registerDeviceIn($company, 'ATIVO-1');
    ['device' => $other] = registerDeviceIn($company, 'ATIVO-2');
    $foreignToken = $other->tokens()->sole();

    $this->deleteJson("/api/v1/companies/{$company->slug}/devices/{$device->uuid}/tokens/{$foreignToken->uuid}")
        ->assertNotFound();

    expect($other->tokens()->count())->toBe(1);
});
