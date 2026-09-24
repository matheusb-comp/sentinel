<?php

use App\Alarms\AlarmStatus;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use App\Models\Company;
use App\Models\Device;
use App\Models\Sensor;

/**
 * A sensor of the company, through a device of its own.
 */
function sensorOf(Company $company): Sensor
{
    return Sensor::factory()->create([
        'device_id' => Device::factory()->create(['company_id' => $company->id]),
    ]);
}

it('watches a sensor with a rule', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $sensor = sensorOf($company);

    $response = $this->postJson(
        "/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors",
        ['sensor_uuids' => [$sensor->uuid]],
    )->assertOk();

    $monitor = $rule->monitors()->sole();

    expect($response->json('data.0'))->toBe([
        'uuid' => $monitor->uuid,
        'status' => 'waiting',
        'status_since' => $monitor->status_since->format('c'),
        'pending_since' => null,
        'sensor' => [
            'uuid' => $sensor->uuid,
            'key' => $sensor->key,
            'description' => $sensor->description,
            'label' => $sensor->label,
            'unit' => $sensor->unit,
            'expected_interval' => $sensor->expected_interval,
            'archived_at' => null,
        ],
    ])->and($monitor->sensor_id)->toBe($sensor->id);
});

it('watches many sensors in one request', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $sensors = collect(range(1, 3))->map(fn (): Sensor => sensorOf($company));

    $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors", [
        'sensor_uuids' => $sensors->pluck('uuid')->all(),
    ])->assertOk()->assertJsonCount(3, 'data');

    expect($rule->monitors()->pluck('sensor_id')->sort()->values()->all())
        ->toBe($sensors->pluck('id')->sort()->values()->all());
});

it('answers with what it started watching, not with what was already there', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $watched = sensorOf($company);
    $new = sensorOf($company);
    $monitor = AlarmMonitor::factory()->create([
        'alarm_rule_id' => $rule->id,
        'sensor_id' => $watched->id,
        'status' => AlarmStatus::Alarm,
    ]);

    $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors", [
        'sensor_uuids' => [$watched->uuid, $new->uuid],
    ])->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sensor.uuid', $new->uuid);

    // The watch that was already there keeps the state it had reached.
    expect($rule->monitors()->count())->toBe(2)
        ->and($monitor->refresh()->status)->toBe(AlarmStatus::Alarm);
});

it('answers with nothing when every sensor was already watched', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $body = ['sensor_uuids' => [sensorOf($company)->uuid]];

    $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors", $body)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors", $body)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect($rule->monitors()->count())->toBe(1);
});

it('refuses the whole request when one sensor is of another company', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $mine = sensorOf($company);
    $foreign = Sensor::factory()->create();

    $this->postJson(
        "/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors",
        ['sensor_uuids' => [$mine->uuid, $foreign->uuid]],
    )->assertUnprocessable()->assertJsonValidationErrors('sensor_uuids.1');

    expect($rule->monitors()->count())->toBe(0);
});

it('refuses a list it cannot read', function (mixed $value, string $invalid) {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);

    $this->postJson(
        "/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors",
        ['sensor_uuids' => $value],
    )->assertUnprocessable()->assertJsonValidationErrors($invalid);
})->with([
    'missing' => [null, 'sensor_uuids'],
    'empty' => [[], 'sensor_uuids'],
    'not a list' => [['a' => '7f000101-0000-4000-8000-000000000000'], 'sensor_uuids'],
    'past the ceiling' => [array_fill(0, 101, '7f000101-0000-4000-8000-000000000000'), 'sensor_uuids'],
    'not a uuid' => [['nope'], 'sensor_uuids.0'],
    'a number' => [[7], 'sensor_uuids.0'],
]);

it('lists what a rule watches, with the status of each', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $watched = AlarmMonitor::factory()->create([
        'alarm_rule_id' => $rule->id,
        'sensor_id' => sensorOf($company)->id,
        'status' => AlarmStatus::Alarm,
    ]);
    AlarmMonitor::factory()->create(['sensor_id' => sensorOf($company)->id]);

    $this->getJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $watched->uuid)
        ->assertJsonPath('data.0.status', 'alarm');
});

it('paginates what a rule watches', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    AlarmMonitor::factory()->count(2)->create([
        'alarm_rule_id' => $rule->id,
        'sensor_id' => fn () => sensorOf($company)->id,
    ]);

    $this->getJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors?per_page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('stops watching the sensors it is given', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $dropped = sensorOf($company);
    $kept = sensorOf($company);
    $monitors = AlarmMonitor::factory()
        ->count(2)
        ->sequence(['sensor_id' => $dropped->id], ['sensor_id' => $kept->id])
        ->create(['alarm_rule_id' => $rule->id]);

    $this->deleteJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors", [
        // A uuid that names nothing is ignored, not an error.
        'sensor_uuids' => [$dropped->uuid, '7f000101-0000-4000-8000-000000000000'],
    ])->assertNoContent();

    expect(AlarmMonitor::find($monitors[0]->id))->toBeNull()
        ->and(AlarmMonitor::find($monitors[1]->id))->not->toBeNull()
        ->and(AlarmRule::find($rule->id))->not->toBeNull();
});

it('does not stop what another rule watches', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $other = AlarmRule::factory()->create(['company_id' => $company->id]);
    $sensor = sensorOf($company);
    $monitor = AlarmMonitor::factory()->create([
        'alarm_rule_id' => $other->id,
        'sensor_id' => $sensor->id,
    ]);

    $this->deleteJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors", [
        'sensor_uuids' => [$sensor->uuid],
    ])->assertNoContent();

    expect(AlarmMonitor::find($monitor->id))->not->toBeNull();
});

it('refuses to stop watching by something that is not a uuid', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);

    $this->deleteJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors", [
        'sensor_uuids' => ['nope'],
    ])->assertUnprocessable()->assertJsonValidationErrors('sensor_uuids.0');
});

it('refuses to watch an archived sensor', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $archived = sensorOf($company);
    Sensor::whereKey($archived->id)->update(['archived_at' => now()]);

    $this->postJson(
        "/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}/monitors",
        ['sensor_uuids' => [$archived->uuid]],
    )->assertUnprocessable()->assertJsonValidationErrors('sensor_uuids.0');

    expect($rule->monitors()->count())->toBe(0);
});
