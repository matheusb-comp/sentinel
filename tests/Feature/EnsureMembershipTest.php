<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Http\Middleware\EnsureMembership;
use App\Http\Middleware\ResolveCompanyByUuid;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

it('runs the tenant route guards in order, before route model binding', function () {
    $route = Route::getRoutes()->match(Request::create('/api/v1/companies/acme'));

    $guards = [
        Authenticate::class,
        EnsureEmailIsVerified::class,
        ResolveCompanyByUuid::class,
        InitializeTenancyByPath::class,
        EnsureMembership::class,
        SubstituteBindings::class,
    ];

    $middleware = array_map(
        fn (string $middleware): string => Str::before($middleware, ':'),
        Route::gatherRouteMiddleware($route),
    );

    expect(array_values(array_intersect($middleware, $guards)))->toBe($guards);
});

it('lets a member reach their company', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $company->slug);
});

it('initializes tenancy for the company in the path', function () {
    $user = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($user, 'First');
    $second = app(CreateCompanyForUser::class)->handle($user, 'Second');

    $this->withToken(issueUserToken($user, $second))
        ->getJson("/api/v1/companies/{$second->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $second->slug);
});

it('serves consecutive requests with tokens of different users and companies', function () {
    $first = User::factory()->create();
    $firstCompany = app(CreateCompanyForUser::class)->handle($first, 'First');
    $second = User::factory()->create();
    $secondCompany = app(CreateCompanyForUser::class)->handle($second, 'Second');

    $this->withToken(issueUserToken($first, $firstCompany))
        ->getJson("/api/v1/companies/{$firstCompany->slug}")
        ->assertOk();

    $this->withToken(issueUserToken($second, $secondCompany))
        ->getJson("/api/v1/companies/{$secondCompany->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $secondCompany->slug);
});

it('lets a session request through, which carries no token of the company', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->actingAs($user)
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertOk();
});

it('returns 404 when the user is not a member of the company', function () {
    $intruder = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($intruder, 'Intruder Co');
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    // A token for this company and an active membership elsewhere, so only the
    // membership lookup rejects them.
    $this->withToken(issueUserToken($intruder, $company))
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertNotFound();
});

it('tells a request without a token nothing about whether a company exists', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    $this->getJson("/api/v1/companies/{$company->slug}")->assertUnauthorized();
    $this->getJson('/api/v1/companies/doesnotexist')->assertUnauthorized();
});

it('refuses a token it does not know', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

    $this->withToken('not-a-token')
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertUnauthorized();
});

it('refuses a device token', function () {
    ['device' => $device, 'token' => $token] = registerDevice();

    $this->withToken($token)
        ->getJson("/api/v1/companies/{$device->company->slug}")
        ->assertUnauthorized();
});

it('tells an unverified user nothing about whether a company exists', function () {
    $user = User::factory()->unverified()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');
    $this->withToken(issueUserToken($user, $company));

    $this->getJson("/api/v1/companies/{$company->slug}")->assertForbidden();
    $this->getJson('/api/v1/companies/doesnotexist')->assertForbidden();
});

it('returns 404 when the membership is inactive', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');
    $company->users()->updateExistingPivot($user->id, ['active' => false]);

    $this->withToken(issueUserToken($user, $company))
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertNotFound();
});

it('returns 404 for an unknown company', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson('/api/v1/companies/doesnotexist')
        ->assertNotFound();
});

it('returns 404 for a deleted company', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');
    $token = issueUserToken($user, $company);
    $company->delete();

    $this->withToken($token)
        ->getJson("/api/v1/companies/{$company->slug}")
        ->assertNotFound();
});

it('refuses a token issued for another company of the same user', function () {
    $user = User::factory()->create();
    $first = app(CreateCompanyForUser::class)->handle($user, 'First');
    $second = app(CreateCompanyForUser::class)->handle($user, 'Second');

    $this->withToken(issueUserToken($user, $first))
        ->getJson("/api/v1/companies/{$second->slug}")
        ->assertNotFound();
});

it('does not accept the numeric key in the path', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->withToken(issueUserToken($user, $company))
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertNotFound();
});
