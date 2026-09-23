<?php

namespace App\Actions\Devices;

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
 * What is declared describes the wanted state: a sensor list is reconciled and
 * a `label` is written on every registration. What is left out is left as it
 * is, so a script that declares neither does not undo what a person set.
 *
 * Every registration issues a new token and leaves the earlier ones valid, so a
 * device can move to a new token without a gap, up to the configured limit.
 */
class CreateDevice
{
    public function __construct(private SyncDeviceSensors $sync) {}

    /**
     * @param  list<array{key: string, description: ?string}>|null  $sensors
     * @return array{device: Device, token: string}
     */
    public function handle(string $key, ?array $sensors = null, ?string $label = null): array
    {
        // Transaction built on the Device connection to cover every write.
        return (new Device)->getConnection()->transaction(function () use ($key, $sensors, $label): array {
            // Locked to prevent race conditions with device archival requests.
            $device = Device::where('key', $key)->lockForUpdate()->first() ?? new Device(['key' => $key]);

            $device->archived_at = null;

            if ($label !== null) {
                $device->label = $label;
            }

            $device->save();

            // Issue before sync to check for token limit first.
            $token = $this->issueToken($device);

            if ($sensors !== null) {
                $this->sync->handle($device, $sensors);
            }

            return ['device' => $device, 'token' => $token];
        });
    }

    /**
     * Removes the numeric ID that Sanctum prefixes to the plain text token.
     * It is not a problem, because `findToken()` can look up by the hash.
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
