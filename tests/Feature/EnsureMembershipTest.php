<?php

use App\Actions\CreateCompanyForUser;
use App\Http\Middleware\EnsureMembership;
use App\Http\Middleware\ResolveCompanyByUuid;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

it('runs the tenant route guards in order, before route model binding', function () {
    $route = Route::getRoutes()->match(Request::create('/companies/acme/ping'));

    $guards = [
        Authenticate::class,
        EnsureEmailIsVerified::class,
        ResolveCompanyByUuid::class,
        InitializeTenancyByPath::class,
        EnsureMembership::class,
        SubstituteBindings::class,
    ];

    $middleware = Route::gatherRouteMiddleware($route);

    expect(array_values(array_intersect($middleware, $guards)))->toBe($guards);
});

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
    $membershipUuid = $company->users()->first()->pivot->uuid;

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/ping")
        ->assertOk()
        ->assertExactJson(['company' => $company->slug, 'membership_uuid' => $membershipUuid]);
});

it('returns 404 when the user is not a member of the company', function () {
    $intruder = User::factory()->create();
    $owner = User::factory()->create();

    // An active membership elsewhere, so only the company_id filter rejects them.
    app(CreateCompanyForUser::class)->handle($intruder, 'Intruder Co');
    $company = app(CreateCompanyForUser::class)->handle($owner, 'Acme');

    $this->actingAs($intruder)
        ->getJson("/companies/{$company->slug}/ping")
        ->assertNotFound();
});

it('tells a guest nothing about whether a company exists', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    $this->getJson("/companies/{$company->slug}/ping")->assertUnauthorized();
    $this->getJson('/companies/doesnotexist/ping')->assertUnauthorized();
});

it('tells an unverified user nothing about whether a company exists', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    $this->actingAs(User::factory()->unverified()->create());

    $this->getJson("/companies/{$company->slug}/ping")->assertForbidden();
    $this->getJson('/companies/doesnotexist/ping')->assertForbidden();
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
