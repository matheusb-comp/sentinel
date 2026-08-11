<?php

use App\Models\Company;
use Illuminate\Support\Facades\Cache;

/**
 * The suites run with CACHE_STORE=array, which CacheTenancyBootstrapper refuses
 * to handle, so these tests point it at the database store instead. That keeps
 * the rest of the suite on the fast in-memory store.
 */
beforeEach(function () {
    config(['tenancy.cache.stores' => ['database']]);
});

it('does not leak a cache entry from one company to another', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    tenancy()->initialize($companyA);
    Cache::store('database')->put('thresholds', 'from-a', 60);
    tenancy()->end();

    tenancy()->initialize($companyB);
    expect(Cache::store('database')->get('thresholds'))->toBeNull();

    Cache::store('database')->put('thresholds', 'from-b', 60);
    tenancy()->end();

    tenancy()->initialize($companyA);
    expect(Cache::store('database')->get('thresholds'))->toBe('from-a');
    tenancy()->end();
});

it('restores the central prefix after tenancy ends', function () {
    $company = Company::factory()->create();

    // getPrefix() lives on the Store, not on the Repository that Cache::store()
    // returns. Repository::__call forwards it at runtime, but going through
    // getStore() is what the declared types actually allow.
    $centralPrefix = Cache::store('database')->getStore()->getPrefix();

    tenancy()->initialize($company);
    $tenantPrefix = Cache::store('database')->getStore()->getPrefix();
    tenancy()->end();

    expect($tenantPrefix)->not->toBe($centralPrefix)
        ->and($tenantPrefix)->toContain((string) $company->id)
        ->and(Cache::store('database')->getStore()->getPrefix())->toBe($centralPrefix);
});

it('keeps a central cache entry invisible inside tenancy', function () {
    $company = Company::factory()->create();

    Cache::store('database')->put('shared', 'central-value', 60);

    tenancy()->initialize($company);
    expect(Cache::store('database')->get('shared'))->toBeNull();
    tenancy()->end();

    expect(Cache::store('database')->get('shared'))->toBe('central-value');
});
