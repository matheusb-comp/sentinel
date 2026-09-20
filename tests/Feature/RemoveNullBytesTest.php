<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::match(['get', 'post'], '/probe-null-bytes', fn (Request $request) => $request->all());
});

it('removes a null byte from the middle of a json value', function () {
    $this->postJson('/probe-null-bytes', ['value' => "te\0st"])
        ->assertExactJson(['value' => 'test']);
});

it('leaves array keys alone', function () {
    $this->postJson('/probe-null-bytes', ['payload' => ["te\0st" => 'x', 'test' => 'y']])
        ->assertExactJson(['payload' => ["te\0st" => 'x', 'test' => 'y']]);
});

it('removes a null byte from a nested value', function () {
    $this->postJson('/probe-null-bytes', ['payload' => ['nested' => "te\0st"]])
        ->assertExactJson(['payload' => ['nested' => 'test']]);
});

it('removes a null byte from the query string', function () {
    $this->get('/probe-null-bytes?value=te%00st')
        ->assertExactJson(['value' => 'test']);
});

it('removes a null byte from a form body', function () {
    $this->post('/probe-null-bytes', ['value' => "te\0st"])
        ->assertExactJson(['value' => 'test']);
});

it('runs before the middleware that exempts credentials from trimming', function () {
    $this->postJson('/probe-null-bytes', ['password' => "pa\0ss"])
        ->assertExactJson(['password' => 'pass']);
});
