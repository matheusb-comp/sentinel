<?php

use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('refuses a token with a key prefix that is not a bigint instead of failing on it', function (string $token) {
    $this->withToken($token)->putJson('/api/v1/sensors', ['sensors' => [['key' => 'temp']]])
        ->assertUnauthorized();
})->with([
    'not a number' => ['abc|def'],
    'past the bigint range' => ['99999999999999999999|def'],
]);

it('hands the device over on the tenant connection', function () {
    ['token' => $token] = registerDevice();
    Route::middleware(['api', AuthenticateDevice::class])->get('/api/probe-device', fn (Request $request) => [
        'connection' => $request->attributes->get(AuthenticateDevice::ATTRIBUTE)->getConnectionName(),
    ]);

    $this->withToken($token)->getJson('/api/probe-device')->assertJsonPath('connection', 'tenant');
});
