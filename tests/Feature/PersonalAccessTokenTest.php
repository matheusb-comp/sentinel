<?php

use App\Models\User;
use Illuminate\Database\QueryException;

it('refuses a token created outside a tenancy, which has no company to belong to', function () {
    $user = User::factory()->create();

    expect(fn () => $user->createToken('test'))->toThrow(QueryException::class);
});
