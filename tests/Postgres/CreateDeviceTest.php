<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Actions\Devices\CreateDevice;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('leaves no device behind when the token cannot be issued inside tenancy', function () {
    config(['ingestion.max_tokens_per_device' => 0]);
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');

    tenancy()->initialize($company);

    $register = fn () => app(CreateDevice::class)->handle('ATIVO-1', [['key' => 'temp', 'description' => null]]);

    expect($register)->toThrow(ValidationException::class);

    // Read past the policies: a rolled back row is gone for everyone, while a
    // row they merely hide would still be holding the unique key.
    $left = Device::on('pgsql_testing')->where('key', 'ATIVO-1')->count();

    tenancy()->end();

    expect($left)->toBe(0);
});

it('leaves no token behind when the registration is rolled back inside tenancy', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');

    tenancy()->initialize($company);

    $register = fn () => DB::transaction(function () {
        app(CreateDevice::class)->handle('ATIVO-1', [['key' => 'temp', 'description' => null]]);

        throw new RuntimeException('Roll the registration back.');
    });

    expect($register)->toThrow(RuntimeException::class, 'Roll the registration back.');

    tenancy()->end();

    expect(PersonalAccessToken::count())->toBe(0);
});

it('registers a device whose key a competing registration inserts first', function () {
    config(['database.connections.rival' => config('database.connections.pgsql_testing')]);
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');

    tenancy()->initialize($company);

    $competed = false;

    // A competing registration, committed on its own connection between the
    // lookup that finds nothing and the insert.
    Device::creating(function (Device $device) use (&$competed): void {
        if ($competed) {
            return;
        }

        $competed = true;

        Device::on('rival')->create(['key' => $device->key]);
    });

    ['device' => $registered, 'token' => $token] = app(CreateDevice::class)->handle('ATIVO-1');

    tenancy()->end();

    // sole() fails if the collision left a second device holding the key.
    $winner = Device::where('key', 'ATIVO-1')->sole();

    expect($registered->id)->toBe($winner->id)
        ->and(PersonalAccessToken::findToken($token)->tokenable_id)->toBe($winner->id);
});

it('registers an existing device again inside tenancy', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');
    ['device' => $device] = registerDeviceIn($company, 'ATIVO-1');

    tenancy()->initialize($company);

    $again = app(CreateDevice::class)->handle('ATIVO-1', [['key' => 'temp', 'description' => null]]);

    tenancy()->end();

    expect($again['device']->id)->toBe($device->id)
        ->and(PersonalAccessToken::findToken($again['token'])->company_id)->toBe($company->id)
        ->and(PersonalAccessToken::where('tokenable_id', $device->id)->count())->toBe(2);
});
