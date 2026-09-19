<?php

namespace App\Actions\Fortify;

use App\Actions\CreateCompanyForUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private CreateCompanyForUser $createCompanyForUser) {}

    /**
     * Validate and create a newly registered user along with their company.
     *
     * A user who belongs to no company has nowhere to go, so both are created
     * in one transaction.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'company_name' => ['required', 'string', 'max:255'],
        ])->validate();

        // Company's connection, not the default: tenancy re-points database.default.
        return (new Company)->getConnection()->transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $this->createCompanyForUser->handle($user, $input['company_name']);

            return $user;
        });
    }
}
