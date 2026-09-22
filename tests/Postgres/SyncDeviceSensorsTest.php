<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Actions\Devices\SyncDeviceSensors;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Database\QueryException;

it('leaves no sensor behind when a later one in the list is rejected', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');
    $device = Device::factory()->create(['company_id' => $company->id]);

    tenancy()->initialize($company);

    $sync = fn () => app(SyncDeviceSensors::class)->handle($device, [
        ['key' => 'temp', 'description' => null],
        ['key' => 'hum', 'description' => str_repeat('x', 300)],
    ]);

    expect($sync)->toThrow(QueryException::class);

    // Read past the policies: a rolled back row is gone for everyone, while a
    // row they merely hide would still be holding the unique key.
    $left = Sensor::on('pgsql_testing')->where('device_id', $device->id)->count();

    tenancy()->end();

    expect($left)->toBe(0);
});
