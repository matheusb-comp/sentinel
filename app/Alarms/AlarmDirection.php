<?php

namespace App\Alarms;

/**
 * Which side of the threshold is a breach: a `high` rule alarms over it, a
 * `low` rule under it.
 */
enum AlarmDirection: string
{
    case High = 'high';
    case Low = 'low';

    /**
     * Whether the value is on the breaching side. The threshold itself is not:
     * a `high` rule of 8 is satisfied by a reading of exactly 8.
     */
    public function breaches(float $value, float $threshold): bool
    {
        return match ($this) {
            self::High => $value > $threshold,
            self::Low => $value < $threshold,
        };
    }
}
