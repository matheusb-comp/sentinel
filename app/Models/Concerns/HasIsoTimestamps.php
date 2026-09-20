<?php

namespace App\Models\Concerns;

/**
 * Stores and serializes dates as ISO-8601 with a numeric offset.
 *
 * Laravel's default storage format carries no zone at all. The offset here is
 * numeric rather than the zone name because a Carbon built in memory renders
 * the name as "UTC" while one hydrated from Postgres renders it as "+00:00",
 * and Eloquent compares those strings to decide whether an attribute changed —
 * so the name marks an untouched timestamp as dirty.
 *
 * `$dateFormat` only reaches the columns a model reports in `getDates()`, which
 * by default is just `created_at` and `updated_at`. A model with other date
 * columns lists them there.
 */
trait HasIsoTimestamps
{
    public const DATE_CAST = 'datetime:c';

    protected $dateFormat = 'c';
}
