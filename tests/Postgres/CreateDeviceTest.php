<?php

use App\Actions\CreateCompanyForUser;
use App\Actions\CreateDevice;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

it('leaves no token behind when the registration is rolled back inside tenancy', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');

    tenancy()->initialize($company);

    $register = fn () => DB::transaction(function () use ($company) {
        app(CreateDevice::class)->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

        throw new RuntimeException('Roll the registration back.');
    });

    expect($register)->toThrow(RuntimeException::class, 'Roll the registration back.');

    tenancy()->end();

    expect(PersonalAccessToken::count())->toBe(0);
});

it('registers an existing device again inside tenancy', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');
    ['device' => $device] = app(CreateDevice::class)->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

    tenancy()->initialize($company);

    $again = app(CreateDevice::class)->handle($company, 'ATIVO-1', [['key' => 'temp', 'description' => null]]);

    tenancy()->end();

    expect($again['device']->id)->toBe($device->id)
        ->and(PersonalAccessToken::findToken($again['token'])->company_id)->toBe($company->id)
        ->and(PersonalAccessToken::where('tokenable_id', $device->id)->count())->toBe(2);
});
