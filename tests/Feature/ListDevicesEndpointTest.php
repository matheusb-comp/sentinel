<?php

use App\Actions\Devices\ArchiveDevice;

it('lists the devices of the company with the archived ones last', function () {
    $company = actingAsMember();
    registerDeviceIn($company, 'ATIVO-2');
    registerDeviceIn($company, 'ATIVO-1');
    ['device' => $archived] = registerDeviceIn($company, 'ATIVO-0');
    app(ArchiveDevice::class)->handle($archived);

    $this->getJson("/api/v1/companies/{$company->slug}/devices")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.key', 'ATIVO-1')
        ->assertJsonPath('data.1.key', 'ATIVO-2')
        ->assertJsonPath('data.2.key', 'ATIVO-0');
});

it('lists only the active devices', function () {
    $company = actingAsMember();
    registerDeviceIn($company, 'ATIVO-1');
    ['device' => $archived] = registerDeviceIn($company, 'ATIVO-2');
    app(ArchiveDevice::class)->handle($archived);

    $this->getJson("/api/v1/companies/{$company->slug}/devices?archived=0")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'ATIVO-1');
});

it('lists only the archived devices', function () {
    $company = actingAsMember();
    registerDeviceIn($company, 'ATIVO-1');
    ['device' => $archived] = registerDeviceIn($company, 'ATIVO-2');
    app(ArchiveDevice::class)->handle($archived);

    $this->getJson("/api/v1/companies/{$company->slug}/devices?archived=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'ATIVO-2');
});

it('lists every device when the filter comes empty', function () {
    $company = actingAsMember();
    registerDeviceIn($company, 'ATIVO-1');
    ['device' => $archived] = registerDeviceIn($company, 'ATIVO-2');
    app(ArchiveDevice::class)->handle($archived);

    $this->getJson("/api/v1/companies/{$company->slug}/devices?archived=")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('refuses a filter that is not a boolean', function () {
    $company = actingAsMember();

    $this->getJson("/api/v1/companies/{$company->slug}/devices?archived=maybe")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archived');
});

it('paginates the devices', function () {
    $company = actingAsMember();
    registerDeviceIn($company, 'ATIVO-1');
    registerDeviceIn($company, 'ATIVO-2');

    $this->getJson("/api/v1/companies/{$company->slug}/devices?per_page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('keeps the filter and the page size in the links', function () {
    $company = actingAsMember();
    ['device' => $first] = registerDeviceIn($company, 'ATIVO-1');
    ['device' => $second] = registerDeviceIn($company, 'ATIVO-2');
    app(ArchiveDevice::class)->handle($first);
    app(ArchiveDevice::class)->handle($second);

    $this->getJson("/api/v1/companies/{$company->slug}/devices?archived=1&per_page=1")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);

    expect($this->getJson("/api/v1/companies/{$company->slug}/devices?archived=1&per_page=1")->json('links.next'))
        ->toContain('archived=1')
        ->toContain('per_page=1');
});

it('falls back to the configured page size, not the one of the model', function () {
    config(['pagination.per_page' => 7]);
    $company = actingAsMember();

    $this->getJson("/api/v1/companies/{$company->slug}/devices?per_page=")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 7);
});

it('refuses a page size above the configured ceiling', function () {
    config(['pagination.max_per_page' => 2]);
    $company = actingAsMember();

    $this->getJson("/api/v1/companies/{$company->slug}/devices?per_page=3")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('does not list the devices of another company', function () {
    $company = actingAsMember();
    registerDevice();

    $this->getJson("/api/v1/companies/{$company->slug}/devices")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
