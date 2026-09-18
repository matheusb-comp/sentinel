<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('emails a reset link that points at the frontend', function () {
    Notification::fake();
    config(['app.frontend_url' => 'https://app.example.com']);
    $user = User::factory()->create(['email' => 'matheus@example.com']);

    $this->postJson('/forgot-password', ['email' => $user->email])->assertSuccessful();

    $notification = Notification::sent($user, ResetPassword::class)->sole();

    expect($notification->toMail($user)->actionUrl)
        ->toBe("https://app.example.com/reset-password/{$notification->token}?email=matheus%40example.com");
});

it('resets the password with the emailed token', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])->assertSuccessful();

    $token = Notification::sent($user, ResetPassword::class)->sole()->token;

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSuccessful();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});
