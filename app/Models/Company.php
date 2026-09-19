<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Database\Concerns;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;

/**
 * The tenant. Uses real columns instead of the package's `data` JSON column,
 * so VirtualColumn is deliberately absent.
 *
 * GeneratesIds is absent as well: the tenant key is an auto-increment bigint,
 * and the methods of that trait collide with the ones HasUuids brings in.
 *
 * HasDatabase is required even though this application is single-database:
 * the tenants:rls command calls $tenant->database() to reuse the package's
 * Postgres user creation logic. No per-tenant database is ever created.
 */
#[Fillable(['name'])]
#[Hidden(['id'])]
class Company extends Model implements Tenant, TenantWithDatabase
{
    /** @use HasFactory<CompanyFactory> */
    use Concerns\CentralConnection,
        Concerns\HasDatabase,
        Concerns\HasInternalKeys,
        Concerns\InitializationHelpers,
        Concerns\InvalidatesResolverCache,
        Concerns\TenantRun,
        HasFactory,
        HasPublicUuid,
        SoftDeletes;

    private const SLUG_LENGTH = 12;

    protected $table = 'companies';

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s e';

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey(): int|string
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    /**
     * Bind and generate routes by slug instead of the uuid.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(CompanyUser::class)
            ->withPivot(['id', 'uuid', 'active'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (self $company): void {
            $company->slug ??= static::generateUniqueSlug();
        });
    }

    /**
     * Soft deleted companies keep their slug, so a released identifier cannot
     * be claimed by someone else.
     */
    protected static function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(self::SLUG_LENGTH);
        } while (static::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:c',
            'updated_at' => 'datetime:c',
            'deleted_at' => 'datetime:c',
        ];
    }
}
