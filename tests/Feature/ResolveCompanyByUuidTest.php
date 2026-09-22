<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\User;
use Illuminate\Support\Str;

it('lets a member reach their company by uuid', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson("/api/v1/companies/{$company->uuid}")
        ->assertOk()
        ->assertJsonPath('data.slug', $company->slug);
});

it('returns 404 when the user is not a member of the company addressed by uuid', function () {
    $intruder = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($intruder, 'Intruder Co');
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    $this->withToken(issueUserToken($intruder, $company))
        ->getJson("/api/v1/companies/{$company->uuid}")
        ->assertNotFound();
});

it('returns 404 for an unknown company uuid', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson('/api/v1/companies/'.Str::uuid7())
        ->assertNotFound();
});
