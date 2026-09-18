<?php

use App\Actions\CreateCompanyForUser;
use App\Models\User;

/**
 * Tenant routes served on the RLS connection, as in production.
 *
 * Once tenancy is initialized, the session, user and membership lookups also
 * run as the RLS role, so a table missing a grant fails the whole request.
 */
it('serves a tenant route end to end on the rls connection', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/ping")
        ->assertOk()
        ->assertJsonPath('company', $company->slug);
});

it('refuses another company end to end on the rls connection', function () {
    $intruder = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($intruder, 'Intruder Co');
    $target = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Target Co');

    $this->actingAs($intruder)
        ->getJson("/companies/{$target->slug}/ping")
        ->assertNotFound();
});

it('tells a guest nothing about whether a company exists', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    $this->getJson("/companies/{$company->slug}/ping")->assertUnauthorized();
    $this->getJson('/companies/doesnotexist/ping')->assertUnauthorized();
});
