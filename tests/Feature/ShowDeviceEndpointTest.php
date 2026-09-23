<?php

it('shows the device with its sensors, the archived ones last', function () {
    $company = actingAsMember();
    ['device' => $device] = registerDeviceIn($company, 'ATIVO-1', ['temp', 'hum']);
    $device->sensors()->where('key', 'hum')->update(['archived_at' => now()]);
    $archived = $device->sensors()->where('key', 'hum')->sole();

    $this->getJson("/api/v1/companies/{$company->slug}/devices/{$device->uuid}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'uuid' => $device->uuid,
            'key' => 'ATIVO-1',
            'label' => null,
            'archived_at' => null,
            'sensors' => [
                [
                    'uuid' => $device->sensors()->where('key', 'temp')->value('uuid'),
                    'key' => 'temp',
                    'description' => null,
                    'label' => null,
                    'unit' => null,
                    'expected_interval' => null,
                    'archived_at' => null,
                ],
                [
                    'uuid' => $archived->uuid,
                    'key' => 'hum',
                    'description' => null,
                    'label' => null,
                    'unit' => null,
                    'expected_interval' => null,
                    'archived_at' => $archived->toArray()['archived_at'],
                ],
            ],
        ]]);
});

it('does not show a device of another company', function () {
    $company = actingAsMember();
    ['device' => $foreign] = registerDevice();

    $this->getJson("/api/v1/companies/{$company->slug}/devices/{$foreign->uuid}")
        ->assertNotFound();
});
