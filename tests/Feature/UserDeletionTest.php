<?php

use App\Actions\CreateCompanyForUser;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Database\QueryException;

it('refuses to delete a user who belongs to a company', function () {
    $user = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($user, 'Acme');

    expect(fn () => $user->delete())->toThrow(QueryException::class);

    expect(User::find($user->id))->not->toBeNull()
        ->and(CompanyUser::where('user_id', $user->id)->exists())->toBeTrue();
});

it('deletes a user who belongs to no company', function () {
    $user = User::factory()->create();

    $user->delete();

    expect(User::find($user->id))->toBeNull();
});
