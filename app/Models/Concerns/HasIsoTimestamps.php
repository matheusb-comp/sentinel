<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores and serializes dates as ISO-8601 with a numeric offset.
 *
 * Laravel's default storage format carries no zone at all. The offset here is
 * numeric rather than the zone name because a Carbon built in memory renders
 * the name as "UTC" while one hydrated from Postgres renders it as "+00:00",
 * and Eloquent compares those strings to decide whether an attribute changed —
 * so the name marks an untouched timestamp as dirty.
 *
 * `getDates()` is where a model declares its date columns, and by default that
 * is just `created_at` and `updated_at`. A model with others lists them there
 * and spreads `isoCasts()` into its own `casts()`, so each column is named once.
 *
 * @mixin Model
 */
trait HasIsoTimestamps
{
    public const DATE_CAST = 'datetime:c';

    protected $dateFormat = 'c';

    /**
     * @return array<string, string>
     */
    protected function isoCasts(): array
    {
        return array_fill_keys($this->getDates(), self::DATE_CAST);
    }
}
