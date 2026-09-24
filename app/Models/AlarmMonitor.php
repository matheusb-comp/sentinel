<?php

namespace App\Models;

use App\Alarms\AlarmStatus;
use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AlarmMonitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToPrimaryModel;

/**
 * One sensor watched against one rule, and where that watch stands.
 *
 * The row is overwritten for as long as the watch exists: a cursor, not a log.
 * `pending_since` is the only mark of a transition in flight, and it reads the
 * same in both directions — the value is on the side opposite the status, and
 * has been since that instant.
 */
#[Fillable(['alarm_rule_id', 'sensor_id', 'status', 'status_since', 'pending_since', 'evaluated_through', 'last_value', 'next_check_at'])]
#[Hidden(['id', 'alarm_rule_id', 'sensor_id'])]
class AlarmMonitor extends Model
{
    /** @use HasFactory<AlarmMonitorFactory> */
    use BelongsToPrimaryModel, HasFactory, HasIsoTimestamps, HasPublicUuid;

    /**
     * Through the rule, which is also the path the generated RLS policy takes,
     * so the two layers scope this table the same way.
     */
    public function getRelationshipToPrimaryModel(): string
    {
        return 'rule';
    }

    /**
     * @return BelongsTo<AlarmRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlarmRule::class, 'alarm_rule_id');
    }

    /**
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * @return list<string>
     */
    public function getDates(): array
    {
        return [
            ...parent::getDates(),
            'status_since',
            'pending_since',
            'evaluated_through',
            'next_check_at',
        ];
    }

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            ...$this->isoCasts(),
            'status' => AlarmStatus::class,
            'last_value' => 'float',
        ];
    }
}
