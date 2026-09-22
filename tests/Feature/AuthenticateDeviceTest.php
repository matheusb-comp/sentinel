<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\RequireJsonBody;
use App\Models\PersonalAccessToken;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function syncWith(?string $token): TestResponse
{
    $request = $token === null ? test() : test()->withToken($token);

    return $request->putJson('/in/v1/sync', ['sensors' => [['key' => 'temp']]]);
}

it('runs the device route guards in order, before route model binding', function () {
    $route = Route::getRoutes()->match(Request::create('/in/v1/sync', 'PUT'));

    $guards = [
        AuthenticateDevice::class,
        RequireJsonBody::class,
        SubstituteBindings::class,
    ];

    $middleware = array_map(
        fn (string $middleware): string => Str::before($middleware, ':'),
        Route::gatherRouteMiddleware($route),
    );

    expect(array_values(array_intersect($middleware, $guards)))->toBe($guards);
});

it('lets a device in with the token it was issued', function () {
    ['token' => $token] = registerDevice();

    syncWith($token)->assertNoContent();
});

it('authenticates a device after a request for another company in the same process', function () {
    ['token' => $first] = registerDevice();
    ['token' => $second] = registerDevice();

    syncWith($first)->assertNoContent();

    syncWith($second)->assertNoContent();
});

it('binds route models only once the tenancy of the device is initialized', function () {
    ['device' => $device, 'token' => $token] = registerDevice();
    ['device' => $other] = registerDevice();
    $own = $device->sensors()->sole()->uuid;
    $foreign = $other->sensors()->sole()->uuid;
    Route::middleware(['api', AuthenticateDevice::class])
        ->get('/api/probe-sensor/{sensor}', fn (Sensor $sensor) => ['uuid' => $sensor->uuid]);

    $this->withToken($token)->getJson("/api/probe-sensor/{$foreign}")->assertNotFound();
    $this->withToken($token)->getJson("/api/probe-sensor/{$own}")->assertOk();
});

it('refuses a request without a token', function () {
    syncWith(null)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
});

it('refuses a wrong token before looking at the body', function () {
    $this->withToken('not-a-token')->put('/in/v1/sync', ['sensors' => [['key' => 'temp']]])
        ->assertUnauthorized();
});

it('refuses a token it does not know', function () {
    syncWith('not-a-token')->assertUnauthorized();
});

it('refuses a token issued to a user whose key matches a device', function () {
    ['device' => $device] = registerDevice();

    $token = new PersonalAccessToken(['name' => 'user', 'token' => hash('sha256', 'user-token'), 'abilities' => ['*']]);
    $token->tokenable()->associate(User::factory()->create(['id' => $device->id]));
    $token->company_id = $device->company_id;
    $token->save();

    syncWith('user-token')->assertUnauthorized();
});

it('refuses a token whose company is not the company of its device', function () {
    ['device' => $device, 'token' => $token] = registerDevice();
    ['device' => $other] = registerDevice();
    $device->tokens()->update(['company_id' => $other->company_id]);

    syncWith($token)->assertUnauthorized();
});

it('refuses an expired token', function () {
    ['device' => $device, 'token' => $token] = registerDevice();
    $device->tokens()->update(['expires_at' => now()->subMinute()]);

    syncWith($token)->assertUnauthorized();
});

it('refuses the token of an archived device', function () {
    ['device' => $device, 'token' => $token] = registerDevice();
    $device->archived_at = now();
    $device->save();

    syncWith($token)->assertUnauthorized();
});

it('refuses the token of a deleted company', function () {
    ['device' => $device, 'token' => $token] = registerDevice();
    $device->company->delete();

    syncWith($token)->assertUnauthorized();
});

it('records when a token is first used', function () {
    $this->freezeTime();
    ['device' => $device, 'token' => $token] = registerDevice();

    syncWith($token);

    expect($device->tokens()->sole()->last_used_at->toIso8601String())->toBe(now()->toIso8601String());
});

it('leaves last_used_at alone within the hour', function () {
    $this->freezeTime();
    ['device' => $device, 'token' => $token] = registerDevice();
    $device->tokens()->update(['last_used_at' => now()->subMinutes(59)]);

    syncWith($token);

    expect($device->tokens()->sole()->last_used_at->toIso8601String())
        ->toBe(now()->subMinutes(59)->toIso8601String());
});

it('records the use again once the hour has passed', function () {
    $this->freezeTime();
    ['device' => $device, 'token' => $token] = registerDevice();
    $device->tokens()->update(['last_used_at' => now()->subMinutes(61)]);

    syncWith($token);

    expect($device->tokens()->sole()->last_used_at->toIso8601String())->toBe(now()->toIso8601String());
});
