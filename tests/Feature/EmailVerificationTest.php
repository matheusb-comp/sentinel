<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('emails a verification link that points at the frontend', function () {
    Notification::fake();
    config(['app.frontend_url' => 'https://app.example.com']);
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    $link = Notification::sent($user, VerifyEmail::class)->sole()->toMail($user)->actionUrl;

    expect($link)->toStartWith("https://app.example.com/email/verify/{$user->id}/");
});

it('verifies the email when the frontend forwards the link to the api', function () {
    Notification::fake();
    config(['app.frontend_url' => 'https://app.example.com']);
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    $link = Notification::sent($user, VerifyEmail::class)->sole()->toMail($user)->actionUrl;

    $this->actingAs($user)
        ->getJson(parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY))
        ->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
