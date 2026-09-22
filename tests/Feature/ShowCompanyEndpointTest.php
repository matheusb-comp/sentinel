<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\User;

it('shows the company in the path', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'uuid' => $company->uuid,
            'slug' => $company->slug,
            'name' => 'Acme',
        ]]);
});
