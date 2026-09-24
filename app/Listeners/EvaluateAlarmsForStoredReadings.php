<?php

namespace App\Listeners;

use App\Actions\Alarms\EvaluateAlarms;
use App\Events\ReadingsStored;

/**
 * Moves the alarm monitors of a batch the ingestion already stored.
 *
 * The evaluation hangs off the event rather than off `IngestReadings`, so a
 * failure on this side leaves the readings where they are.
 */
class EvaluateAlarmsForStoredReadings
{
    public function __construct(private EvaluateAlarms $evaluate) {}

    public function handle(ReadingsStored $event): void
    {
        $this->evaluate->handle($event->readings);
    }
}
