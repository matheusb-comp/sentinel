<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ordering for a model retired by an `archived_at` date instead of deleted.
 */
trait HasArchivedState
{
    /**
     * Rows still in service first, retired ones after them.
     */
    public function scopeActiveFirst(Builder $query): void
    {
        $query->orderByRaw($query->qualifyColumn('archived_at').' is not null');
    }
}
