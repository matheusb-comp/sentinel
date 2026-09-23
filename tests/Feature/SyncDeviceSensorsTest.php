<?php

use App\Actions\Devices\SyncDeviceSensors;
use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Support\Facades\DB;

it('creates what is new and updates the description of what exists', function () {
    $device = Device::factory()->create();
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp', 'description' => 'old']);

    app(SyncDeviceSensors::class)->handle($device, [
        ['key' => 'temp', 'description' => 'DS18B20'],
        ['key' => 'hum', 'description' => 'DHT22'],
    ]);

    expect($device->sensors()->count())->toBe(2)
        ->and($device->sensors()->where('key', 'temp')->value('description'))->toBe('DS18B20')
        ->and($device->sensors()->where('key', 'hum')->value('description'))->toBe('DHT22');
});

it('archives a sensor the device stopped declaring', function () {
    $device = Device::factory()->create();
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp']);
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'hum']);

    app(SyncDeviceSensors::class)->handle($device, [['key' => 'temp', 'description' => null]]);

    expect($device->sensors()->whereNotNull('archived_at')->pluck('key')->all())->toBe(['hum']);
});

it('brings an archived sensor back when the device declares it again', function () {
    $device = Device::factory()->create();
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp', 'archived_at' => now()]);

    app(SyncDeviceSensors::class)->handle($device, [['key' => 'temp', 'description' => null]]);

    expect($device->sensors()->where('key', 'temp')->value('archived_at'))->toBeNull();
});

it('never overwrites the label a person set', function () {
    $device = Device::factory()->create();
    Sensor::factory()->create([
        'device_id' => $device->id,
        'key' => 'temp',
        'label' => 'Câmara fria 3 / porta',
    ]);

    app(SyncDeviceSensors::class)->handle($device, [['key' => 'temp', 'description' => 'DS18B20']]);

    expect($device->sensors()->where('key', 'temp')->value('label'))->toBe('Câmara fria 3 / porta');
});

it('leaves the sensors of another device alone', function () {
    $device = Device::factory()->create();
    $other = Device::factory()->create();
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp']);
    Sensor::factory()->create(['device_id' => $other->id, 'key' => 'temp']);

    app(SyncDeviceSensors::class)->handle($device, [['key' => 'hum', 'description' => null]]);

    expect($other->sensors()->whereNotNull('archived_at')->count())->toBe(0);
});

it('does not query per sensor when the declaration did not change', function () {
    $device = Device::factory()->create();
    $declared = [];

    foreach (['temp', 'hum', 'co2', 'lux', 'volt'] as $key) {
        Sensor::factory()->create(['device_id' => $device->id, 'key' => $key, 'description' => null]);
        $declared[] = ['key' => $key, 'description' => null];
    }

    DB::enableQueryLog();

    app(SyncDeviceSensors::class)->handle($device, $declared);

    // One read of the declared keys, one sweep of the ones left out.
    expect(DB::getQueryLog())->toHaveCount(2);
});

it('archives every sensor when the device declares an empty list', function () {
    $device = Device::factory()->create();
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'temp']);
    Sensor::factory()->create(['device_id' => $device->id, 'key' => 'hum']);

    app(SyncDeviceSensors::class)->handle($device, []);

    expect($device->sensors()->whereNull('archived_at')->count())->toBe(0);
});
