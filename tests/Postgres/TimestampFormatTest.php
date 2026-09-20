<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

it('leaves a model clean when a date column is reassigned the same instant', function () {
    $user = User::factory()->create();

    $fresh = User::find($user->id);
    $fresh->created_at = CarbonImmutable::parse($fresh->created_at->format('Y-m-d H:i:s'), 'UTC');
    $fresh->email_verified_at = CarbonImmutable::parse($fresh->email_verified_at->format('Y-m-d H:i:s'), 'UTC');

    expect($fresh->isDirty())->toBeFalse();
});

it('reports every date column of the model, not only the timestamps', function () {
    expect(User::factory()->make()->getDates())
        ->toBe(['created_at', 'updated_at', 'email_verified_at'])
        ->and(Company::factory()->make()->getDates())
        ->toBe(['created_at', 'updated_at', 'deleted_at'])
        ->and((new Device)->getDates())
        ->toBe(['created_at', 'updated_at', 'archived_at'])
        ->and((new Sensor)->getDates())
        ->toBe(['created_at', 'updated_at', 'archived_at']);
});

it('serializes a date column outside the timestamps with an offset', function (Model $model) {
    $fresh = $model::find($model->id);
    $fresh->archived_at = CarbonImmutable::parse($fresh->archived_at->format('Y-m-d H:i:s'), 'UTC');

    expect($fresh->isDirty())->toBeFalse()
        ->and($model->toArray()['archived_at'])->toEndWith('+00:00');
})->with([
    'device' => fn () => Device::factory()->create(['archived_at' => now()]),
    'sensor' => fn () => Sensor::factory()->create(['archived_at' => now()]),
]);
