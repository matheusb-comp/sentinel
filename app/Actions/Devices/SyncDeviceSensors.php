<?php

namespace App\Actions\Devices;

use App\Actions\Alarms\CloseAlarmPeriods;
use App\Alarms\AlarmEndReason;
use App\Models\AlarmMonitor;
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
 *
 * Archiving a sensor stops its readings from being accepted, so the alarm
 * periods open on it are ended rather than left hanging.
 */
class SyncDeviceSensors
{
    public function __construct(private CloseAlarmPeriods $closeAlarmPeriods) {}

    /**
     * @param  list<array{key: string, description: ?string}>  $sensors
     */
    public function handle(Device $device, array $sensors): void
    {
        // The device's own connection, which its relations write on, so the
        // transaction covers them.
        $device->getConnection()->transaction(function () use ($device, $sensors): void {
            // Archiving comes first so that alarm_monitors is locked before any
            // sensor row is written. ArchiveDevice takes the two in that same
            // order, and the reverse here would deadlock against it.
            //
            // An empty list archives all sensors of a device.
            $leftOut = $device->sensors()
                ->whereNotIn('key', array_column($sensors, 'key'))
                ->whereNull('archived_at')
                ->pluck('id');

            // A device that declares what it declared last time leaves nothing
            // out, which is every sync but the few that change the hardware.
            if ($leftOut->isNotEmpty()) {
                $this->closeAlarmPeriods->handle(
                    AlarmMonitor::whereIn('sensor_id', $leftOut)->pluck('id')->all(),
                    AlarmEndReason::Archived,
                );

                $device->sensors()->whereIn('id', $leftOut)->update(['archived_at' => now()]);
            }

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

                // So that a key declared twice in the same list is updated the
                // second time instead of inserted again.
                $existing[$declared['key']] = $sensor;
            }
        });
    }
}
