<?php

namespace App\Models;

use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What a client registers, and what its sensors hang from.
 *
 * A logical origin, not necessarily a piece of hardware: whatever writes
 * readings is a device, be it a board, a gateway or a script.
 */
#[Fillable(['key', 'label'])]
#[Hidden(['id', 'company_id'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasIsoTimestamps, HasPublicUuid;

    /**
     * The owning tenant, under the name the rest of the application uses for it.
     * BelongsToTenant reaches the same row through tenant().
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Sensor, $this>
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
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
        return $this->isoCasts();
    }
}
