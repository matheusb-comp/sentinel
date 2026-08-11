<?php

use App\Actions\CreateCompanyForUser;
use App\Models\User;

it('lets a member reach their company', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/ping")
        ->assertOk()
        ->assertJsonPath('company', $company->slug);
});

it('initializes tenancy for the company in the path', function () {
    $user = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($user, 'First');
    $second = app(CreateCompanyForUser::class)->handle($user, 'Second');

    $this->actingAs($user)
        ->getJson("/companies/{$second->slug}/ping")
        ->assertOk()
        ->assertJsonPath('company', $second->slug);
});

it('shares the membership as the current actor', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');
    $membershipId = $company->users()->first()->pivot->id;

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/ping")
        ->assertOk()
        ->assertJsonPath('membership', $membershipId);
});

it('returns 404 when the user is not a member of the company', function () {
    $intruder = User::factory()->create();
    $owner = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($owner, 'Acme');

    $this->actingAs($intruder)
        ->getJson("/companies/{$company->slug}/ping")
        ->assertNotFound();
});

it('returns 404 when the membership is inactive', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');
    $company->users()->updateExistingPivot($user->id, ['active' => false]);

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/ping")
        ->assertNotFound();
});

it('returns 404 for an unknown company', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/companies/doesnotexist/ping')
        ->assertNotFound();
});

it('does not accept the numeric key in the path', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->actingAs($user)
        ->getJson("/companies/{$company->id}/ping")
        ->assertNotFound();
});
