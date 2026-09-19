<?php

use App\Actions\CreateCompanyForUser;
use App\Models\User;
use Illuminate\Support\Str;

it('lets a member reach their company by uuid', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->actingAs($user)
        ->getJson("/companies/{$company->uuid}/ping")
        ->assertOk()
        ->assertJsonPath('company', $company->slug);
});

it('returns 404 when the user is not a member of the company addressed by uuid', function () {
    $intruder = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($intruder, 'Intruder Co');
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    $this->actingAs($intruder)
        ->getJson("/companies/{$company->uuid}/ping")
        ->assertNotFound();
});

it('returns 404 for an unknown company uuid', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/companies/'.Str::uuid7().'/ping')
        ->assertNotFound();
});
