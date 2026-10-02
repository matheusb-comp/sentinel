<?php

namespace App\Models;

use App\Alarms\AlarmDirection;
use App\Alarms\AlarmEndReason;
use App\Alarms\AlarmType;
use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonInterface;
use Database\Factories\AlarmPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What happened during a stretch of time a sensor spent in alarm.
 *
 * The condition that judged is copied onto the row, so the period stays readable
 * after the rule is edited or deleted. `type` is what says which kind of
 * condition the other copied columns describe.
 *
 * The row survives its monitor, which is why `alarm_monitor_id` is nullable:
 * unwatching a sensor must not erase what happened while it was watched.
 */
#[Fillable([
    'sensor_id',
    'alarm_monitor_id',
    'breached_at',
    'started_at',
    'ended_at',
    'ended_reason',
    'value',
    'type',
    'label',
    'direction',
    'threshold',
    'trigger_after',
    'clear_after',
])]
#[Hidden(['id', 'company_id', 'sensor_id', 'alarm_monitor_id'])]
class AlarmPeriod extends Model
{
    /** @use HasFactory<AlarmPeriodFactory> */
    use BelongsToTenant, HasFactory, HasIsoTimestamps, HasPublicUuid;

    /**
     * Ends the period, never before it began.
     *
     * `started_at` is on the device's clock, while the instant offered is capped
     * at ours when it comes from a monitor's cursor and on the device's when it
     * comes from a transition. A device running ahead of us closes the period
     * backwards either way, and a reading older than the start is one the cursor
     * no longer filters (ALM-D11).
     */
    public function endAt(?CarbonInterface $at, AlarmEndReason $reason): void
    {
        $this->ended_at = $at !== null && $at->isAfter($this->started_at) ? $at : $this->started_at;
        $this->ended_reason = $reason;
    }

    /**
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * @return BelongsTo<AlarmMonitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(AlarmMonitor::class, 'alarm_monitor_id');
    }

    /**
     * @return list<string>
     */
    public function getDates(): array
    {
        return [
            ...parent::getDates(),
            'breached_at',
            'started_at',
            'ended_at',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->isoCasts(),
            'ended_reason' => AlarmEndReason::class,
            'value' => 'float',
            'type' => AlarmType::class,
            'direction' => AlarmDirection::class,
            'threshold' => 'float',
            'trigger_after' => 'integer',
            'clear_after' => 'integer',
        ];
    }
}
