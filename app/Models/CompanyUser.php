<?php

namespace App\Models;

use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A user's profile within a company.
 *
 * This is the actor for everything that happens inside a tenant: the User is a
 * central identity used for logging in, this is who acts once a company has
 * been entered.
 */
#[Hidden(['id', 'company_id', 'user_id'])]
class CompanyUser extends Pivot
{
    use BelongsToTenant, HasIsoTimestamps, HasPublicUuid;

    protected $table = 'company_user';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->isoCasts(),
            'active' => 'boolean',
        ];
    }
}
