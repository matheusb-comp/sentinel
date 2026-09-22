<?php

namespace App\Models;

use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A Sanctum token, with the public uuid, hidden keys and date format of every
 * other model.
 *
 * The owner is polymorphic, with no key the tenancy can follow, so the token
 * carries its owner's company_id, copied when the token is created.
 */
#[Hidden(['id', 'company_id', 'token', 'tokenable_type', 'tokenable_id'])]
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use BelongsToTenant, HasIsoTimestamps, HasPublicUuid;

    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            $token->company_id ??= $token->tokenable->company_id;
        });
    }

    /**
     * Sanctum reads what comes before a `|` as the token's numeric key, and on
     * Postgres a key that does not fit a bigint is an error rather than no match.
     *
     * @param  string  $token
     */
    public static function findToken($token): ?static
    {
        if (str_contains($token, '|') && filter_var(Str::before($token, '|'), FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return parent::findToken($token);
    }

    /**
     * @return list<string>
     */
    public function getDates(): array
    {
        return [...parent::getDates(), 'last_used_at', 'expires_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->isoCasts();
    }
}
