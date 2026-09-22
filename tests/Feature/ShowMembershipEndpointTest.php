<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\CompanyUser;
use App\Models\User;

it('shows the membership of the caller in the company in the path', function () {
    $user = User::factory()->create(['name' => 'Matheus', 'email' => 'matheus@example.com']);
    app(CreateCompanyForUser::class)->handle($user, 'First');
    $second = app(CreateCompanyForUser::class)->handle($user, 'Second');
    $membership = CompanyUser::where('company_id', $second->id)->sole();

    $this->withToken(issueUserToken($user, $second))
        ->getJson("/api/v1/companies/{$second->slug}/membership")
        ->assertOk()
        ->assertExactJson(['data' => [
            'uuid' => $membership->uuid,
            'user' => [
                'uuid' => $user->uuid,
                'name' => 'Matheus',
                'email' => 'matheus@example.com',
            ],
        ]]);
});
