<?php

use App\Models\Company;
use Illuminate\Database\QueryException;

it('uses an auto-increment integer as the tenant key', function () {
    $company = Company::factory()->create(['name' => 'Acme']);

    expect($company->id)->toBeInt()
        ->and($company->getTenantKey())->toBe($company->id)
        ->and($company->getTenantKeyName())->toBe('id');
});

it('generates a short non guessable slug', function () {
    $company = Company::factory()->create();

    expect($company->slug)->toBeString()->toHaveLength(12)
        ->and($company->slug)->toMatch('/^[A-Za-z0-9]+$/');
});

it('never generates the same slug twice', function () {
    $slugs = Company::factory()->count(25)->create()->pluck('slug');

    expect($slugs->unique())->toHaveCount(25);
});

it('binds routes by slug, not by the numeric key', function () {
    $company = Company::factory()->create();

    expect($company->getRouteKeyName())->toBe('slug')
        ->and($company->getRouteKey())->toBe($company->slug);
});

it('hides the numeric key from serialization', function () {
    $company = Company::factory()->create();

    expect($company->toArray())->not->toHaveKey('id')
        ->and($company->toArray())->toHaveKey('slug');
});

it('keeps an explicitly provided slug', function () {
    $company = Company::factory()->create(['slug' => 'chosen-name']);

    expect($company->slug)->toBe('chosen-name');
});

it('initializes tenancy and reverts to central context', function () {
    $company = Company::factory()->create();

    tenancy()->initialize($company);
    expect(tenant()->id)->toBe($company->id);

    tenancy()->end();
    expect(tenant())->toBeNull();
});

it('stops resolving a soft deleted company', function () {
    $company = Company::factory()->create();
    $company->delete();

    expect(Company::find($company->id))->toBeNull()
        ->and(Company::withTrashed()->find($company->id))->not->toBeNull();
});

it('does not free the slug of a soft deleted company', function () {
    $company = Company::factory()->create(['slug' => 'taken']);
    $company->delete();

    expect(fn () => Company::factory()->create(['slug' => 'taken']))
        ->toThrow(QueryException::class);
});
