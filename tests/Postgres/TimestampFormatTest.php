<?php

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

it('leaves a model clean when a date column is reassigned the same instant', function (Model $model, string $column) {
    $fresh = $model::find($model->id);
    $fresh->{$column} = CarbonImmutable::parse($fresh->{$column}->format('Y-m-d H:i:s'), 'UTC');

    expect($fresh->isDirty())->toBeFalse();
})->with([
    'a timestamp' => fn () => [User::factory()->create(), 'created_at'],
    'a column of the user' => fn () => [User::factory()->create(), 'email_verified_at'],
    'a column of the device' => fn () => [Device::factory()->create(['archived_at' => now()]), 'archived_at'],
    'a column of the sensor' => fn () => [Sensor::factory()->create(['archived_at' => now()]), 'archived_at'],
]);
