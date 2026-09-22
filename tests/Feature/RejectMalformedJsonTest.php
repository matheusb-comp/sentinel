<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::match(['get', 'post'], '/probe-json-body', fn () => ['reached' => true]);
});

it('refuses a body declared as JSON that does not parse', function () {
    $this->call('POST', '/probe-json-body', server: $this->transformHeadersToServerVars([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ]), content: '{"value": ')
        ->assertStatus(400)
        ->assertJsonPath('message', 'The body is not valid JSON.');
});

it('lets through a request declared as JSON without a body', function () {
    $this->call('GET', '/probe-json-body', server: $this->transformHeadersToServerVars([
        'Content-Type' => 'application/json',
    ]))->assertOk();
});

it('leaves a body that is not declared as JSON alone', function () {
    $this->call('POST', '/probe-json-body', server: $this->transformHeadersToServerVars([
        'Content-Type' => 'text/plain',
    ]), content: '{"value": ')->assertOk();
});
