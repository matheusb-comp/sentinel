<?php

use App\Actions\CreateCompanyForUser;
use App\Actions\CreateDevice;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\QueryException;

it('leaves no device behind when a sensor is rejected inside tenancy', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');

    tenancy()->initialize($company);

    $create = fn () => app(CreateDevice::class)->handle($company, 'ATIVO-1', [
        ['key' => 'temp', 'description' => null],
        ['key' => 'temp', 'description' => null],
    ]);

    expect($create)->toThrow(QueryException::class);

    // Read past the policies: a rolled back row is gone for everyone, while a
    // row they merely hide would still be holding the unique key.
    $left = Device::on('pgsql_testing')->where('key', 'ATIVO-1')->count();

    tenancy()->end();

    expect($left)->toBe(0);
});
