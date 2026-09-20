<?php

use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;

it('leaves a model clean when a date column is reassigned the same instant', function () {
    $user = User::factory()->create();

    $fresh = User::find($user->id);
    $fresh->created_at = CarbonImmutable::parse($fresh->created_at->format('Y-m-d H:i:s'), 'UTC');
    $fresh->email_verified_at = CarbonImmutable::parse($fresh->email_verified_at->format('Y-m-d H:i:s'), 'UTC');

    expect($fresh->isDirty())->toBeFalse();
});

it('reports every date column of the model, not only the timestamps', function () {
    expect(User::factory()->make()->getDates())
        ->toBe(['created_at', 'updated_at', 'email_verified_at'])
        ->and(Company::factory()->make()->getDates())
        ->toBe(['created_at', 'updated_at', 'deleted_at']);
});
