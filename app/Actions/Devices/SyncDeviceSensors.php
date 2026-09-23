<?php

namespace App\Actions\Devices;

use App\Models\Device;

/**
 * Reconciles a device's sensors with the list the device declares.
 *
 * A key that disappears is archived rather than deleted, because its readings
 * outlive it. A key that comes back is un-archived: the ordinary case is a probe
 * replaced in the field and reinstalled under the same name.
 *
 * `label` is never written here. It belongs to whoever named the sensor in the
 * application, and a device update must not undo that.
 */
class SyncDeviceSensors
{
    /**
     * @param  list<array{key: string, description: ?string}>  $sensors
     */
    public function handle(Device $device, array $sensors): void
    {
        // The device's own connection, which its relations write on, so the
        // transaction covers them.
        $device->getConnection()->transaction(function () use ($device, $sensors): void {
            $existing = $device->sensors()
                ->whereIn('key', array_column($sensors, 'key'))
                ->get()
                ->keyBy('key');

            foreach ($sensors as $declared) {
                $sensor = $existing[$declared['key']] ?? $device->sensors()->make(['key' => $declared['key']]);

                $sensor->description = $declared['description'] ?? null;
                $sensor->archived_at = null;
                // An unchanged sensor is not dirty, so this writes nothing.
                $sensor->save();
            }

            // An empty list matches every sensor, so a device that declares
            // none archives all of them.
            $device->sensors()
                ->whereNotIn('key', array_column($sensors, 'key'))
                ->whereNull('archived_at')
                ->update(['archived_at' => now()]);
        });
    }
}
