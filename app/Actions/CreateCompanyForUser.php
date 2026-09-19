<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\User;

/**
 * Creates a company and makes the given user its first member.
 *
 * Kept separate from registration on purpose: a User may also arrive through
 * Laravel Socialite, and both paths must converge on this action.
 */
class CreateCompanyForUser
{
    public function handle(User $user, string $name): Company
    {
        // Company's connection, not the default: tenancy re-points database.default.
        return (new Company)->getConnection()->transaction(function () use ($user, $name): Company {
            $company = Company::create(['name' => $name]);

            $company->users()->attach($user, ['active' => true]);

            return $company;
        });
    }
}
