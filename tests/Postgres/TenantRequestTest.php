<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\User;

/**
 * Tenant routes served on the RLS connection, as in production.
 *
 * Once tenancy is initialized, the membership, its user and everything after
 * them are read as the RLS role, so a table missing a grant fails the whole
 * request.
 */
it('serves a tenant route end to end on the rls connection', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $company->slug);
});

it('refuses another company end to end on the rls connection', function () {
    $intruder = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($intruder, 'Intruder Co');
    $target = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Target Co');

    $this->withToken(issueUserToken($intruder, $target))
        ->getJson("/api/v1/companies/{$target->slug}")
        ->assertNotFound();
});

it('shows the membership end to end on the rls connection', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson("/api/v1/companies/{$company->slug}/membership")
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid);
});
