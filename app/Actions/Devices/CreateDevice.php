<?php

namespace App\Actions\Devices;

use App\Models\Company;
use App\Models\Device;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registers a device with its sensors, and issues it a token.
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
 *
 * Every registration issues a new token and leaves the earlier ones valid, so a
 * device can move to a new token without a gap, up to the configured limit.
 */
class CreateDevice
{
    /**
     * @param  list<array{key: string, description: ?string}>  $sensors
     * @return array{device: Device, token: string}
     */
    public function handle(Company $company, string $key, array $sensors, ?string $label = null): array
    {
        // Built from Device so every write lands on the connection this
        // transaction covers: Company is pinned to the central connection, and
        // its relations inherit that.
        return (new Device)->getConnection()->transaction(function () use ($company, $key, $sensors, $label): array {
            // Locked to prevent race conditions with device archival requests.
            $device = Device::where('company_id', $company->id)->where('key', $key)->lockForUpdate()->first();

            if ($device !== null) {
                $device->archived_at = null;
                $device->save();

                return ['device' => $device, 'token' => $this->issueToken($device)];
            }

            $device = new Device(['key' => $key, 'label' => $label]);
            $device->company_id = $company->id;
            $device->save();

            $device->sensors()->createMany($sensors);

            return ['device' => $device, 'token' => $this->issueToken($device)];
        });
    }

    /**
     * Sanctum prefixes the plain text with the token's numeric key and a `|`;
     * the device gets what follows, which findToken() looks up by its hash.
     */
    private function issueToken(Device $device): string
    {
        if ($device->tokens()->count() >= config('ingestion.max_tokens_per_device')) {
            throw ValidationException::withMessages([
                'key' => 'This device holds the maximum number of tokens. Revoke one before registering it again.',
            ]);
        }

        return Str::after($device->createToken('device')->plainTextToken, '|');
    }
}
