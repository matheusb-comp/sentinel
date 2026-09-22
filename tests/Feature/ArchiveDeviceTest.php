<?php

use App\Actions\Devices\ArchiveDevice;
use App\Actions\Devices\CreateDevice;
use App\Models\PersonalAccessToken;

it('archives the device and deletes its tokens', function () {
    ['device' => $device, 'token' => $token] = registerDevice();

    app(ArchiveDevice::class)->handle($device);

    expect($device->fresh()->archived_at)->not->toBeNull()
        ->and(PersonalAccessToken::findToken($token))->toBeNull();
});

it('leaves the tokens of another device of the same company alone', function () {
    ['device' => $device] = registerDevice();
    ['token' => $otherToken] = app(CreateDevice::class)->handle($device->company, 'ATIVO-OTHER', []);

    app(ArchiveDevice::class)->handle($device);

    expect(PersonalAccessToken::findToken($otherToken))->not->toBeNull();
});
