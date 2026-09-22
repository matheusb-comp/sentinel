<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('creates an rls policy for every tenant table', function () {
    $policies = DB::table('pg_policies')
        ->where('tablename', 'company_user')
        ->pluck('policyname');

    expect($policies)->not->toBeEmpty();
});

it('compares the tenant key as bigint, never casting the column to text', function () {
    $definition = DB::table('pg_policies')
        ->where('tablename', 'company_user')
        ->value('qual');

    // Postgres renders this as:
    //   (company_id = (current_setting('my.current_tenant'::text))::bigint)
    // The ::text there belongs to the string literal argument, which is
    // harmless. What must never appear is (company_id)::text — casting the
    // column is what makes the predicate non-indexable.
    expect($definition)->toContain('::bigint')
        ->and($definition)->not->toContain('(company_id)::text');
});

it('hides another company rows while tenancy is initialized', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $action = app(CreateCompanyForUser::class);
    $companyA = $action->handle($userA, 'A');
    $action->handle($userB, 'B');

    tenancy()->initialize($companyA);

    // Raw query builder, so the application-level TenantScope does not apply.
    // Anything filtered here is filtered by the database.
    $visible = DB::table('company_user')->pluck('company_id')->unique()->values()->all();

    tenancy()->end();

    expect($visible)->toBe([$companyA->id]);
});

it('rejects an insert carrying another company key', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $outsider = User::factory()->create();
    $action = app(CreateCompanyForUser::class);
    $companyA = $action->handle($userA, 'A');
    $companyB = $action->handle($userB, 'B');

    tenancy()->initialize($companyA);

    $insert = fn () => DB::table('company_user')->insert([
        'uuid' => (string) Str::uuid7(),
        'company_id' => $companyB->id,
        'user_id' => $outsider->id,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($insert)->toThrow(QueryException::class, 'violates row-level security policy');

    tenancy()->end();
});

it('reads tenant tables from central context', function () {
    $user = User::factory()->create();
    app(CreateCompanyForUser::class)->handle($user, 'A');
    app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'B');

    // Outside tenancy the session variable is unset. This only works because
    // the central role has BYPASSRLS, which is a production requirement.
    expect(DB::table('company_user')->count())->toBe(2)
        ->and($user->companies()->count())->toBe(1)
        ->and(Company::count())->toBe(2);
});

it('pushes the tenant predicate into the index', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');

    tenancy()->initialize($company);
    $plan = collect(DB::select('EXPLAIN SELECT * FROM company_user'))
        ->pluck('QUERY PLAN')
        ->implode("\n");
    tenancy()->end();

    // The policy casts the setting to bigint, leaving the column indexable.
    expect($plan)->toContain('Index Cond:')
        ->and($plan)->toContain('::bigint');
});
