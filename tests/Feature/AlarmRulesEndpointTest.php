<?php

use App\Alarms\AlarmDirection;
use App\Alarms\AlarmType;
use App\Models\AlarmMonitor;
use App\Models\AlarmRule;
use App\Models\Sensor;

it('creates a rule for the company in the path', function () {
    $company = actingAsMember();

    $response = $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules", [
        'direction' => 'high',
        'threshold' => 8.5,
        'trigger_after' => 600,
    ])->assertCreated();

    $rule = AlarmRule::sole();

    expect($response->json('data'))->toBe([
        'uuid' => $rule->uuid,
        'label' => null,
        'direction' => 'high',
        'threshold' => 8.5,
        'trigger_after' => 600,
        'clear_after' => 0,
        'max_reading_age' => null,
        'active' => true,
    ])
        ->and($rule->company_id)->toBe($company->id)
        ->and($rule->type)->toBe(AlarmType::Threshold);
});

it('creates a rule with every setting given', function () {
    $company = actingAsMember();

    $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules", [
        'label' => 'Freezer, norma interna',
        'direction' => 'low',
        'threshold' => -20,
        'trigger_after' => 0,
        'clear_after' => 300,
        'max_reading_age' => 60,
        'active' => 0,
    ])->assertCreated()
        ->assertJsonPath('data.label', 'Freezer, norma interna')
        ->assertJsonPath('data.clear_after', 300)
        ->assertJsonPath('data.max_reading_age', 60)
        ->assertJsonPath('data.active', false);
});

it('refuses a rule it could not evaluate', function (array $body, string $invalid) {
    $company = actingAsMember();

    $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules", [
        'direction' => 'high',
        'threshold' => 8.0,
        'trigger_after' => 600,
        ...$body,
    ])->assertUnprocessable()->assertJsonValidationErrors($invalid);
})->with([
    'no direction' => [['direction' => null], 'direction'],
    'unknown direction' => [['direction' => 'sideways'], 'direction'],
    'no threshold' => [['threshold' => null], 'threshold'],
    'threshold as text' => [['threshold' => 'cold'], 'threshold'],
    'no duration' => [['trigger_after' => null], 'trigger_after'],
    'negative duration' => [['trigger_after' => -1], 'trigger_after'],
    'duration past a year' => [['trigger_after' => 31622401], 'trigger_after'],
    'negative recovery' => [['clear_after' => -1], 'clear_after'],
    'reading age of zero' => [['max_reading_age' => 0], 'max_reading_age'],
    'active as text' => [['active' => 'yes'], 'active'],
    'label past the column' => [['label' => str_repeat('x', 256)], 'label'],
]);

it('lists the rules of the company, newest first', function () {
    $company = actingAsMember();
    $older = AlarmRule::factory()->create(['company_id' => $company->id, 'threshold' => 1.0]);
    $newer = AlarmRule::factory()->create(['company_id' => $company->id, 'threshold' => 2.0]);
    AlarmRule::factory()->create();

    $this->getJson("/api/v1/companies/{$company->slug}/alarm-rules")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.uuid', $newer->uuid)
        ->assertJsonPath('data.1.uuid', $older->uuid);
});

it('paginates the rules', function () {
    $company = actingAsMember();
    AlarmRule::factory()->count(2)->create(['company_id' => $company->id]);

    $this->getJson("/api/v1/companies/{$company->slug}/alarm-rules?per_page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('deletes a rule and the monitors that apply it', function () {
    $company = actingAsMember();
    $rule = AlarmRule::factory()->create(['company_id' => $company->id]);
    $monitor = AlarmMonitor::factory()->create([
        'alarm_rule_id' => $rule->id,
        'sensor_id' => Sensor::factory(),
    ]);

    $this->deleteJson("/api/v1/companies/{$company->slug}/alarm-rules/{$rule->uuid}")
        ->assertNoContent();

    expect(AlarmRule::find($rule->id))->toBeNull()
        ->and(AlarmMonitor::find($monitor->id))->toBeNull();
});

it('does not reach a rule of another company', function () {
    $company = actingAsMember();
    $foreign = AlarmRule::factory()->create(['direction' => AlarmDirection::Low]);

    $this->deleteJson("/api/v1/companies/{$company->slug}/alarm-rules/{$foreign->uuid}")
        ->assertNotFound();

    expect(AlarmRule::find($foreign->id))->not->toBeNull();
});

it('refuses a threshold that overflows a double', function () {
    $company = actingAsMember();

    $this->call('POST', "/api/v1/companies/{$company->slug}/alarm-rules", server: $this->transformHeadersToServerVars([
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]), content: '{"direction": "high", "threshold": 1e400, "trigger_after": 600}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('threshold');
});

it('refuses a setting sent as null', function (string $field) {
    $company = actingAsMember();

    $this->postJson("/api/v1/companies/{$company->slug}/alarm-rules", [
        'direction' => 'high',
        'threshold' => 8.0,
        'trigger_after' => 600,
        $field => null,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['clear_after', 'active']);
