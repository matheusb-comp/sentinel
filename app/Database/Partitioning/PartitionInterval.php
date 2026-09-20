<?php

namespace App\Database\Partitioning;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The width of a time range partition, and the name a range of that width gets.
 *
 * The suffix formats are from pg_partman: a date for widths of a day or more,
 * date and time below that. A partition is named after the start of the range
 * it covers, which is how both this class and that extension locate a partition
 * without reading its bounds.
 */
enum PartitionInterval: string
{
    case Hour = '1 hour';
    case Day = '1 day';
    case Week = '1 week';
    case Month = '1 month';

    /** The start of the range that holds the given moment. */
    public function start(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Hour => $moment->startOfHour(),
            self::Day => $moment->startOfDay(),
            self::Week => $moment->startOfWeek(CarbonInterface::MONDAY),
            self::Month => $moment->startOfMonth(),
        };
    }

    /** The start of the range after the given one, which is also its end. */
    public function next(CarbonImmutable $start): CarbonImmutable
    {
        return match ($this) {
            self::Hour => $start->addHour(),
            self::Day => $start->addDay(),
            self::Week => $start->addWeek(),
            self::Month => $start->addMonth(),
        };
    }

    public function suffix(CarbonImmutable $start): string
    {
        return $start->format($this->format());
    }

    /** Null when the suffix was not written by suffix(). */
    public function parse(string $suffix): ?CarbonImmutable
    {
        if (preg_match($this->pattern(), $suffix) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!'.$this->format(), $suffix, 'UTC');
    }

    private function format(): string
    {
        return $this === self::Hour ? 'Ymd_His' : 'Ymd';
    }

    private function pattern(): string
    {
        return $this === self::Hour ? '/^\d{8}_\d{6}$/' : '/^\d{8}$/';
    }
}
