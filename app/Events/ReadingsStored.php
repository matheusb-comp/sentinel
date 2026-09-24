<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A batch of readings that reached the series tables.
 *
 * Carries the readings in the shape StoreReadings received them, and is held
 * until the commit, so nothing reacts to rows a rollback took away.
 */
class ReadingsStored implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<array{sensor_id: int, time: string, value: float}>  $readings
     */
    public function __construct(public array $readings) {}
}
