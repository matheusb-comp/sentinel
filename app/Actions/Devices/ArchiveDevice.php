<?php

namespace App\Actions\Devices;

use App\Actions\Alarms\CloseAlarmPeriods;
use App\Alarms\AlarmEndReason;
use App\Models\AlarmMonitor;
use App\Models\Device;

/**
 * Archives a device with the sensors it still has, and deletes its tokens.
 * To reactivate and issue a new token, the device must be registered again.
 *
 * A device already archived keeps its dates, which say when it left service.
 *
 * Archiving a sensor stops its readings from being accepted, so the alarm
 * periods open on it are ended rather than left hanging.
 */
class ArchiveDevice
{
    public function __construct(private CloseAlarmPeriods $closeAlarmPeriods) {}

    public function handle(Device $device): void
    {
        // Transaction built on the Device connection to cover every write.
        $device->getConnection()->transaction(function () use ($device): void {
            // Locked and read again, so that a registration running at the same
            // time either finishes first, and this archives what it reactivated,
            // or waits and reactivates the device afterwards.
            $locked = Device::whereKey($device->getKey())->lockForUpdate()->sole();

            if ($locked->archived_at === null) {
                $ids = $locked->sensors()->whereNull('archived_at')->select('id');
                $this->closeAlarmPeriods->handle(
                    AlarmMonitor::whereIn('sensor_id', $ids)
                        ->pluck('id')
                        ->all(),
                    AlarmEndReason::Archived,
                );

                $locked->archived_at = now();
                $locked->save();

                $locked->sensors()->whereNull('archived_at')->update(['archived_at' => $locked->archived_at]);
            }

            $device->tokens()->delete();
        });
    }
}
