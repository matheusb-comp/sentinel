<?php

namespace App\Actions\Devices;

use App\Models\Device;

/**
 * Archives a device and deletes its tokens, so nothing it holds can write, not
 * even after the device is reactivated: registering it again issues a new one.
 */
class ArchiveDevice
{
    public function handle(Device $device): void
    {
        // The device's own connection, which its relations write on, so the
        // transaction covers them.
        $device->getConnection()->transaction(function () use ($device): void {
            $device->archived_at = now();
            $device->save();

            $device->tokens()->delete();
        });
    }
}
