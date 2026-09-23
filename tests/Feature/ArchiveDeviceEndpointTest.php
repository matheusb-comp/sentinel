<?php

use App\Models\PersonalAccessToken;

it('archives the device, its sensors and its tokens', function () {
    $company = actingAsMember();
    ['device' => $device, 'token' => $token] = registerDeviceIn($company, 'ATIVO-1');

    $this->deleteJson("/api/v1/companies/{$company->slug}/devices/{$device->uuid}")
        ->assertNoContent();

    expect($device->fresh()->archived_at)->not->toBeNull()
        ->and($device->sensors()->sole()->archived_at)->not->toBeNull()
        ->and(PersonalAccessToken::findToken($token))->toBeNull();
});

it('stops the archived device from writing', function () {
    $company = actingAsMember();
    ['device' => $device, 'token' => $token] = registerDeviceIn($company, 'ATIVO-1');

    $this->deleteJson("/api/v1/companies/{$company->slug}/devices/{$device->uuid}")
        ->assertNoContent();

    $this->withToken($token)
        ->putJson('/in/v1/sync', ['sensors' => [['key' => 'temp']]])
        ->assertUnauthorized();
});

it('does not archive a device of another company', function () {
    $company = actingAsMember();
    ['device' => $foreign] = registerDevice();

    $this->deleteJson("/api/v1/companies/{$company->slug}/devices/{$foreign->uuid}")
        ->assertNotFound();

    expect($foreign->fresh()->archived_at)->toBeNull();
});
