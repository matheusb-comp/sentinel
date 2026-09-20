<?php

namespace App\Models;

use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SensorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToPrimaryModel;

/**
 * One numeric series: one value per instant.
 *
 * The tenant is reached through its device, so there is no company_id here.
 *
 * Three names with three owners: `key` is what the device calls it in every
 * payload, `description` is what the device says it is, and `label` is what a
 * person called it here.
 */
#[Fillable(['key', 'description', 'label', 'unit', 'expected_interval'])]
#[Hidden(['id', 'device_id'])]
class Sensor extends Model
{
    /** @use HasFactory<SensorFactory> */
    use BelongsToPrimaryModel, HasFactory, HasIsoTimestamps, HasPublicUuid;

    public function getRelationshipToPrimaryModel(): string
    {
        return 'device';
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return list<string>
     */
    public function getDates(): array
    {
        return [...parent::getDates(), 'archived_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->isoCasts(),
            'expected_interval' => 'integer',
        ];
    }
}
