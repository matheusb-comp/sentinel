<?php

use App\Actions\Alarms\EvaluateAlarms;
use App\Actions\Readings\IngestReadings;
use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * The clock is frozen at 2026-09-21 12:00:00 UTC, so the window runs from
 * 2026-09-14 12:00:00 to 2026-09-21 12:05:00, both ends included.
 * 1789988400 is 2026-09-21 11:00:00 UTC as a Unix timestamp.
 */

beforeEach(function () {
    config(['ingestion.backfill' => '7 days', 'ingestion.future_tolerance' => '5 minutes']);
    $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00:00', 'UTC'));
    createSeriesPartitions(config('ingestion.backfill'));
});

/**
 * @param  list<mixed>  $readings
 */
function ingest(string $token, array $readings): TestResponse
{
    return test()->withToken($token)->postJson('/in/v1/data', ['readings' => $readings]);
}

it('stores the valid readings and reports the others by position', function () {
    ['token' => $token] = registerDevice(['temp']);

    ingest($token, [
        ['key' => 'temp', 'time' => '2026-09-21T11:00:00Z', 'value' => 4.2],
        ['key' => 'nope', 'time' => '2026-09-21T11:00:00Z', 'value' => 4.2],
        ['key' => 'temp', 'time' => '2026-09-21T11:00:00Z', 'value' => 9.9],
    ])->assertOk()->assertExactJson([
        'stored' => 1,
        'duplicates' => 1,
        'rejected' => [['index' => 1, 'reason' => 'unknown_key']],
    ]);

    expect(DB::table('readings')->pluck('value')->all())->toBe([4.2]);
});

it('counts a resent batch as duplicates', function () {
    ['token' => $token] = registerDevice(['temp']);
    $batch = [['key' => 'temp', 'time' => '2026-09-21T11:00:00Z', 'value' => 4.2]];

    ingest($token, $batch);

    ingest($token, $batch)->assertExactJson(['stored' => 0, 'duplicates' => 1, 'rejected' => []]);
});

it('refuses an item for the first check it fails', function (mixed $item, string $reason) {
    ['token' => $token] = registerDevice(['temp']);

    ingest($token, [$item])->assertExactJson([
        'stored' => 0,
        'duplicates' => 0,
        'rejected' => [['index' => 0, 'reason' => $reason]],
    ]);
})->with([
    'not an object' => ['temp', 'malformed'],
    'a list' => [['temp', '2026-09-21T11:00:00Z', 4.2], 'malformed'],
    'no key' => [['time' => '2026-09-21T11:00:00Z', 'value' => 4.2], 'invalid_key'],
    'empty key' => [['key' => '', 'time' => '2026-09-21T11:00:00Z', 'value' => 4.2], 'invalid_key'],
    'numeric key' => [['key' => 7, 'time' => '2026-09-21T11:00:00Z', 'value' => 4.2], 'invalid_key'],
    'undeclared key' => [['key' => 'nope', 'time' => '2026-09-21T11:00:00Z', 'value' => 4.2], 'unknown_key'],
    'no time' => [['key' => 'temp', 'value' => 4.2], 'invalid_time'],
    'time as a JSON number' => [['key' => 'temp', 'time' => 1789988400000000, 'value' => 4.2], 'invalid_time'],
    'time without Z' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00', 'value' => 4.2], 'invalid_time'],
    'time with an offset' => [['key' => 'temp', 'time' => '2026-09-21T08:00:00-03:00', 'value' => 4.2], 'invalid_time'],
    'time with a zero offset' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00+00:00', 'value' => 4.2], 'invalid_time'],
    'time with a zone abbreviation' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00CST', 'value' => 4.2], 'invalid_time'],
    'time with a space' => [['key' => 'temp', 'time' => '2026-09-21 11:00:00Z', 'value' => 4.2], 'invalid_time'],
    'seven fraction digits' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00.1234567Z', 'value' => 4.2], 'invalid_time'],
    'epoch in milliseconds' => [['key' => 'temp', 'time' => '1789988400000', 'value' => 4.2], 'invalid_time'],
    'impossible date' => [['key' => 'temp', 'time' => '2026-02-30T11:00:00Z', 'value' => 4.2], 'invalid_time'],
    'older than the window' => [['key' => 'temp', 'time' => '2026-09-14T11:59:59.999999Z', 'value' => 4.2], 'time_too_old'],
    'past the future tolerance' => [['key' => 'temp', 'time' => '2026-09-21T12:05:00.000001Z', 'value' => 4.2], 'time_in_future'],
    'no value' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00Z'], 'invalid_value'],
    'value as a string' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00Z', 'value' => '4.2'], 'invalid_value'],
    'value as a boolean' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00Z', 'value' => true], 'invalid_value'],
    'value past 2^53 - 1' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00Z', 'value' => 9007199254740992], 'value_out_of_range'],
    'value below -(2^53 - 1)' => [['key' => 'temp', 'time' => '2026-09-21T11:00:00Z', 'value' => -9007199254740992], 'value_out_of_range'],
    'undeclared key and no time' => [['key' => 'nope', 'value' => 4.2], 'unknown_key'],
    'old time and no value' => [['key' => 'temp', 'time' => '2026-09-01T00:00:00Z'], 'time_too_old'],
]);

it('refuses a value that overflows a double', function () {
    ['token' => $token] = registerDevice(['temp']);

    $this->call('POST', '/in/v1/data', server: $this->transformHeadersToServerVars([
        'Authorization' => "Bearer {$token}",
        'Content-Type' => 'application/json',
    ]), content: '{"readings": [{"key": "temp", "time": "2026-09-21T11:00:00Z", "value": 1e400}]}')
        ->assertJsonPath('rejected', [['index' => 0, 'reason' => 'value_out_of_range']]);
});

it('stores the instant each time format names', function (string $time, string $stored) {
    ['token' => $token] = registerDevice(['temp']);

    ingest($token, [['key' => 'temp', 'time' => $time, 'value' => 1]])->assertJsonPath('stored', 1);

    expect(CarbonImmutable::parse(DB::table('readings')->value('time'))->utc()->format('Y-m-d H:i:s.u'))
        ->toBe($stored);
})->with([
    'ISO, six digits, Z' => ['2026-09-21T11:00:00.123456Z', '2026-09-21 11:00:00.123456'],
    'ISO, three digits' => ['2026-09-21T11:00:00.123Z', '2026-09-21 11:00:00.123000'],
    'ISO, no fraction' => ['2026-09-21T11:00:00Z', '2026-09-21 11:00:00.000000'],
    'epoch in microseconds' => ['1789988400123456', '2026-09-21 11:00:00.123456'],
]);

it('accepts the largest value and a reading at either edge of the window', function () {
    ['token' => $token] = registerDevice(['temp']);

    ingest($token, [
        ['key' => 'temp', 'time' => '2026-09-14T12:00:00Z', 'value' => 9007199254740991],
        ['key' => 'temp', 'time' => '2026-09-21T12:05:00Z', 'value' => -9007199254740991],
    ])->assertExactJson(['stored' => 2, 'duplicates' => 0, 'rejected' => []]);

    expect(DB::table('readings')->orderBy('time')->pluck('value')->all())
        ->toBe([9007199254740991.0, -9007199254740991.0]);
});

it('refuses an archived sensor like one never declared', function () {
    ['device' => $device, 'token' => $token] = registerDevice(['temp', 'hum']);
    $device->sensors()->where('key', 'hum')->update(['archived_at' => now()]);

    ingest($token, [
        ['key' => 'hum', 'time' => '2026-09-21T11:00:00Z', 'value' => 1],
        ['key' => 'nope', 'time' => '2026-09-21T11:00:00Z', 'value' => 1],
    ])->assertJsonPath('rejected', [
        ['index' => 0, 'reason' => 'unknown_key'],
        ['index' => 1, 'reason' => 'unknown_key'],
    ]);
});

it('moves the monitor of a sensor it stored readings for', function () {
    ['token' => $token, 'device' => $device] = registerDevice(['temp']);
    $watch = AlarmMonitor::factory()->create([
        'alarm_rule_id' => AlarmRule::factory()->create([
            'company_id' => $device->company_id,
            'trigger_after' => 0,
        ])->id,
        'sensor_id' => $device->sensors()->sole()->id,
    ]);

    ingest($token, [['key' => 'temp', 'time' => '2026-09-21T11:59:00Z', 'value' => 9.0]])->assertOk();

    expect($watch->refresh()->status)->toBe(AlarmStatus::Alarm);
});

it('keeps the readings it stored when the evaluation fails', function () {
    ['device' => $device] = registerDevice(['temp']);

    $this->mock(EvaluateAlarms::class)
        ->shouldReceive('handle')
        ->andThrow(new RuntimeException('Break the evaluation.'));

    $ingest = fn () => $device->company->run(fn () => app(IngestReadings::class)->handle($device, [
        ['key' => 'temp', 'time' => '2026-09-21T11:59:00Z', 'value' => 9.0],
    ]));

    expect($ingest)->toThrow(RuntimeException::class, 'Break the evaluation.')
        ->and(DB::table('readings')->count())->toBe(1);
});
