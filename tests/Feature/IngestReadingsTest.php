<?php

use App\Actions\Readings\IngestReadings;

it('refuses an empty key', function () {
    ['device' => $device] = registerDevice(['temp']);

    $result = app(IngestReadings::class)->handle($device, [['key' => '', 'time' => '2026-09-21T11:00:00Z', 'value' => 1]]);

    expect($result['rejected'])->toBe([['index' => 0, 'reason' => 'invalid_key']]);
});

it('refuses a time followed by a line break', function (string $time) {
    ['device' => $device] = registerDevice(['temp']);

    $result = app(IngestReadings::class)->handle($device, [['key' => 'temp', 'time' => $time, 'value' => 1]]);

    expect($result['rejected'])->toBe([['index' => 0, 'reason' => 'invalid_time']]);
})->with([
    'epoch' => ["1789988400123456\n"],
    'ISO' => ["2026-09-21T11:00:00Z\n"],
]);
