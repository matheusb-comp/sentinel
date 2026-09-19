<?php

use App\Actions\CreateCompanyForUser;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a company and an active membership for the user', function () {
    $user = User::factory()->create();

    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    expect($company->name)->toBe('Acme')
        ->and($user->companies()->pluck('companies.id')->all())->toBe([$company->id]);

    $membership = CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->active)->toBeTrue();
});

it('rolls back the company when the membership cannot be created', function () {
    $user = User::factory()->create();
    $user->delete();

    expect(fn () => app(CreateCompanyForUser::class)->handle($user, 'Orphan'))
        ->toThrow(QueryException::class);

    expect(Company::where('name', 'Orphan')->exists())->toBeFalse();
});

it('lets one user belong to several companies', function () {
    $user = User::factory()->create();
    $action = app(CreateCompanyForUser::class);

    $action->handle($user, 'First');
    $action->handle($user, 'Second');

    expect($user->companies()->count())->toBe(2);
});

it('lets one company have several users', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();

    $company = app(CreateCompanyForUser::class)->handle($owner, 'Acme');
    $company->users()->attach($colleague, ['active' => true]);

    expect($company->users()->count())->toBe(2);
});

it('refuses the same user twice in one company', function () {
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    expect(fn () => $company->users()->attach($user, ['active' => true]))
        ->toThrow(QueryException::class);
});

it('scopes memberships to the current company once tenancy is initialized', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $action = app(CreateCompanyForUser::class);
    $companyA = $action->handle($userA, 'A');
    $action->handle($userB, 'B');

    expect(CompanyUser::count())->toBe(2);

    // TenantScope stays registered: the configured manager is TableRLSManager
    // and no model implements RLSModel, so both layers filter.
    tenancy()->initialize($companyA);
    expect(CompanyUser::count())->toBe(1)
        ->and(CompanyUser::first()->company_id)->toBe($companyA->id);
    tenancy()->end();

    expect(CompanyUser::count())->toBe(2);
});
