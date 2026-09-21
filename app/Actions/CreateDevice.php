<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Device;

/**
 * Registers a device together with the sensors it declared.
 *
 * Registering a key that is already taken returns the device that holds it, so
 * a provisioning script that runs twice converges instead of duplicating. An
 * archived device comes back the same way: the key is immutable, so it can only
 * mean that device.
 *
 * The declared list is read only while the device is being created. Reconciling
 * it afterwards is SyncDeviceSensors, which the device drives; and `label`
 * belongs to whoever named the device here, so a later registration does not
 * overwrite it.
 */
class CreateDevice
{
    /**
     * @param  list<array{key: string, description: ?string}>  $sensors
     */
    public function handle(Company $company, string $key, array $sensors, ?string $label = null): Device
    {
        // Built from Device so every write lands on the connection this
        // transaction covers: Company is pinned to the central connection, and
        // its relations inherit that.
        return (new Device)->getConnection()->transaction(function () use ($company, $key, $sensors, $label): Device {
            $device = Device::where('company_id', $company->id)->where('key', $key)->first();

            if ($device !== null) {
                $device->archived_at = null;
                $device->save();

                return $device;
            }

            $device = new Device(['key' => $key, 'label' => $label]);
            $device->company_id = $company->id;
            $device->save();

            $device->sensors()->createMany($sensors);

            return $device;
        });
    }
}
