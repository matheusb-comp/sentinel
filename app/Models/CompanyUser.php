<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A user's profile within a company.
 *
 * This is the actor for everything that happens inside a tenant: the User is a
 * central identity used for logging in, this is who acts once a company has
 * been entered.
 */
class CompanyUser extends Pivot
{
    use BelongsToTenant;

    protected $table = 'company_user';

    public $incrementing = true;

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s e';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'created_at' => 'datetime:c',
            'updated_at' => 'datetime:c',
        ];
    }
}
