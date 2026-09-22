<?php

namespace App\Actions;

use App\Models\Device;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Validates a batch of readings item by item and stores the valid ones.
 *
 * An invalid item is left out and reported by its position, so one bad reading
 * does not hold back the batch. The first check an item fails names the reason.
 */
class IngestReadings
{
    /**
     * The largest magnitude up to which a double holds every integer exactly.
     * Past it, a counter would be stored as a different number.
     */
    private const MAX_MAGNITUDE = 9007199254740991;

    public function __construct(private StoreReadings $storeReadings) {}

    /**
     * @param  array<int, mixed>  $items
     * @return array{stored: int, duplicates: int, rejected: list<array{index: int, reason: string}>}
     */
    public function handle(Device $device, array $items): array
    {
        $sensorIds = $device->sensors()->whereNull('archived_at')->pluck('id', 'key')->all();
        $now = CarbonImmutable::now('UTC');
        $earliest = $now->sub(CarbonInterval::make(config('ingestion.backfill')));
        $latest = $now->add(CarbonInterval::make(config('ingestion.future_tolerance')));

        $accepted = [];
        $rejected = [];

        foreach ($items as $index => $item) {
            $reading = $this->read($item, $sensorIds, $earliest, $latest);

            if (is_string($reading)) {
                $rejected[] = ['index' => $index, 'reason' => $reading];
            } else {
                $accepted[] = $reading;
            }
        }

        $stored = $this->storeReadings->handle($accepted);

        return ['stored' => $stored, 'duplicates' => count($accepted) - $stored, 'rejected' => $rejected];
    }

    /**
     * Validate and format an item to what StoreReadings expects.
     *
     * @param  array<array-key, int>  $sensorIds  the device's active sensors, by key
     * @return array{sensor_id: int, time: string, value: float}|string
     */
    private function read(mixed $item, array $sensorIds, CarbonImmutable $earliest, CarbonImmutable $latest): array|string
    {
        if (! is_array($item) || ($item !== [] && array_is_list($item))) {
            return 'malformed';
        }

        $key = $item['key'] ?? null;

        if (! is_string($key) || $key === '') {
            return 'invalid_key';
        }

        if (! array_key_exists($key, $sensorIds)) {
            return 'unknown_key';
        }

        $time = $this->parseTime($item['time'] ?? null);

        if ($time === null) {
            return 'invalid_time';
        }

        if ($time->lessThan($earliest)) {
            return 'time_too_old';
        }

        if ($time->greaterThan($latest)) {
            return 'time_in_future';
        }

        $value = $item['value'] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            return 'invalid_value';
        }

        // Negated so that NaN, which fails every comparison, is refused too.
        if (! (abs($value) <= self::MAX_MAGNITUDE)) {
            return 'value_out_of_range';
        }

        return [
            'sensor_id' => $sensorIds[$key],
            'time' => $time->format('Y-m-d H:i:s.uP'),
            'value' => (float) $value,
        ];
    }

    /**
     * The instant `time` names, or null when it is in neither accepted format or
     * names a date that does not exist.
     *
     * ISO 8601 in UTC, ending in `Z`, with up to six fraction digits, or a Unix
     * timestamp in microseconds written as exactly 16 digits.
     */
    private function parseTime(mixed $time): ?CarbonImmutable
    {
        if (! is_string($time)) {
            return null;
        }

        if (strlen($time) === 16 && ctype_digit($time)) {
            $parsed = substr($time, 0, 10).'.'.substr($time, 10);
            return CarbonImmutable::createFromFormat('U.u', $parsed, 'UTC');
        }

        foreach (['Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:s.u\Z'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $time, 'UTC');
            } catch (InvalidFormatException) {
                continue;
            }

            // A date that does not exist, such as February 30, parses as one in
            // the next month and is only reported as a warning.
            return CarbonImmutable::getLastErrors() === false ? $parsed : null;
        }

        return null;
    }
}
