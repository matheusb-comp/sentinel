<?php

use App\Http\Requests\Ingest\V1\IngestReadingsRequest;

it('refuses a batch that is not a list of one to the maximum number of readings', function (array $body) {
    ['token' => $token] = registerDevice();

    $this->withToken($token)->postJson('/in/v1/data', $body)
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

    $this->withToken($token)->post('/in/v1/data', ['readings' => [['key' => 'temp']]])
        ->assertStatus(415);
});

it('refuses a body that is not valid JSON', function () {
    ['token' => $token] = registerDevice();

    $this->call('POST', '/in/v1/data', server: $this->transformHeadersToServerVars([
        'Authorization' => "Bearer {$token}",
        'Content-Type' => 'application/json',
    ]), content: '{"readings": [')->assertStatus(400);
});
