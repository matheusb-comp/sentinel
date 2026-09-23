<?php

use App\Actions\Devices\ArchiveDevice;
use App\Models\Device;
use App\Models\PersonalAccessToken;

it('archives the device and deletes its tokens', function () {
    ['device' => $device, 'token' => $token] = registerDevice();

    app(ArchiveDevice::class)->handle($device);

    expect($device->fresh()->archived_at)->not->toBeNull()
        ->and(PersonalAccessToken::findToken($token))->toBeNull();
});

it('archives the active sensors with the device', function () {
    ['device' => $device] = registerDevice(['temp', 'hum']);
    $device->sensors()->where('key', 'hum')->update(['archived_at' => now()->subDay()]);

    app(ArchiveDevice::class)->handle($device);

    $device->refresh();

    expect($device->sensors()->where('key', 'temp')->sole()->archived_at)->toEqual($device->archived_at)
        ->and($device->sensors()->where('key', 'hum')->sole()->archived_at->toDateTimeString())
        ->toBe(now()->subDay()->toDateTimeString());
});

it('keeps the first archival date when the device is archived again', function () {
    ['device' => $device] = registerDevice();

    app(ArchiveDevice::class)->handle($device);
    $archivedAt = $device->fresh()->archived_at;

    $this->travel(1)->hours();
    app(ArchiveDevice::class)->handle($device->fresh());

    expect($device->fresh()->archived_at)->toEqual($archivedAt)
        ->and($device->sensors()->sole()->archived_at)->toEqual($archivedAt);
});

it('archives a device that was reactivated after it was loaded', function () {
    ['device' => $device] = registerDevice();
    app(ArchiveDevice::class)->handle($device);

    $stale = $device->fresh();

    // What a registration between the two calls does, leaving the instance in
    // hand saying archived.
    Device::whereKey($device->getKey())->update(['archived_at' => null]);

    app(ArchiveDevice::class)->handle($stale);

    expect($device->fresh()->archived_at)->not->toBeNull();
});

it('leaves the tokens of another device of the same company alone', function () {
    ['device' => $device] = registerDevice();
    ['token' => $otherToken] = registerDeviceIn($device->company, 'ATIVO-OTHER');

    app(ArchiveDevice::class)->handle($device);

    expect(PersonalAccessToken::findToken($otherToken))->not->toBeNull();
});
