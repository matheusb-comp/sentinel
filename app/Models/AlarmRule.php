<?php

namespace App\Models;

use App\Alarms\AlarmDirection;
use App\Alarms\AlarmType;
use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AlarmRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * How to watch a sensor: which side of which value is wrong, and for how long
 * it has to stay that way to matter.
 *
 * A rule names no sensor. It is configuration owned by the Company, and an
 * AlarmMonitor is what applies it to one sensor — so the same rule covers a
 * hundred freezers, and the threshold is edited in one place.
 */
#[Fillable(['label', 'type', 'direction', 'threshold', 'trigger_after', 'clear_after', 'max_reading_age', 'active'])]
#[Hidden(['id', 'company_id'])]
class AlarmRule extends Model
{
    /** @use HasFactory<AlarmRuleFactory> */
    use BelongsToTenant, HasFactory, HasIsoTimestamps, HasPublicUuid;

    /**
     * The owning tenant, under the name the rest of the application uses for it.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<AlarmMonitor, $this>
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(AlarmMonitor::class);
    }

    /** Seconds after which a reading no longer moves this rule's monitors. */
    public function maxReadingAge(): int
    {
        return $this->max_reading_age ?? config('alarms.max_reading_age');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->isoCasts(),
            'type' => AlarmType::class,
            'direction' => AlarmDirection::class,
            'threshold' => 'float',
            'trigger_after' => 'integer',
            'clear_after' => 'integer',
            'max_reading_age' => 'integer',
            'active' => 'boolean',
        ];
    }
}
