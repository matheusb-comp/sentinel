<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;

it('rolls back the company when called from inside another company context', function () {
    $existing = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Existing');

    $user = User::factory()->create();
    $user->delete();

    // Tenancy points database.default at the RLS connection.
    tenancy()->initialize($existing);

    expect(fn () => app(CreateCompanyForUser::class)->handle($user, 'Orphan'))
        ->toThrow(QueryException::class);

    tenancy()->end();

    expect(Company::where('name', 'Orphan')->exists())->toBeFalse();
});
