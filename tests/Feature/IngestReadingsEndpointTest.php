<?php

use App\Http\Requests\Api\V1\IngestReadingsRequest;

it('refuses a batch that is not a list of one to the maximum number of readings', function (array $body) {
    ['token' => $token] = registerDevice();

    $this->withToken($token)->postJson('/api/v1/readings', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('readings');
})->with([
    'missing' => [[]],
    'empty' => [['readings' => []]],
    'not a list' => [['readings' => ['a' => ['key' => 'temp']]]],
    'over the maximum' => [['readings' => array_fill(0, IngestReadingsRequest::MAX_READINGS + 1, ['key' => 'temp'])]],
]);

it('refuses a body sent without a JSON content type', function () {
    ['token' => $token] = registerDevice();

    $this->withToken($token)->post('/api/v1/readings', ['readings' => [['key' => 'temp']]])
        ->assertStatus(415);
});

it('refuses a body that is not valid JSON', function () {
    ['token' => $token] = registerDevice();

    $this->call('POST', '/api/v1/readings', server: $this->transformHeadersToServerVars([
        'Authorization' => "Bearer {$token}",
        'Content-Type' => 'application/json',
    ]), content: '{"readings": [')->assertStatus(400);
});
