<?php

use App\Actions\CreateCompanyForUser;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('creates a user, a company and an active membership', function () {
    $this->postJson('/register', [
        'name' => 'Matheus',
        'email' => 'matheus@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
        'company_name' => 'Acme',
    ])->assertSuccessful();

    $user = User::where('email', 'matheus@example.com')->firstOrFail();
    $company = Company::where('name', 'Acme')->firstOrFail();

    $membership = CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->active)->toBeTrue()
        ->and($this->isAuthenticated())->toBeTrue();
});

it('requires a company name', function () {
    $this->postJson('/register', [
        'name' => 'Matheus',
        'email' => 'matheus@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ])->assertJsonValidationErrorFor('company_name');

    expect(User::count())->toBe(0)
        ->and(Company::count())->toBe(0);
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/register', [
        'name' => 'Matheus',
        'email' => 'taken@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
        'company_name' => 'Acme',
    ])->assertJsonValidationErrorFor('email');

    expect(Company::count())->toBe(0);
});

it('does not leave an orphan user when the company cannot be created', function () {
    $this->mock(CreateCompanyForUser::class)
        ->shouldReceive('handle')
        ->andThrow(new RuntimeException('boom'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/register', [
        'name' => 'Matheus',
        'email' => 'matheus@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
        'company_name' => 'Acme',
    ]))->toThrow(RuntimeException::class);

    expect(User::count())->toBe(0);
});

it('sends a verification email on registration', function () {
    Notification::fake();

    $this->postJson('/register', [
        'name' => 'Matheus',
        'email' => 'matheus@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
        'company_name' => 'Acme',
    ])->assertSuccessful();

    Notification::assertSentTo(User::firstOrFail(), VerifyEmail::class);
});

it('does not let an unverified user reach their own company', function () {
    $this->postJson('/register', [
        'name' => 'Matheus',
        'email' => 'matheus@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
        'company_name' => 'Acme',
    ])->assertSuccessful();

    $company = User::firstOrFail()->companies()->firstOrFail();

    $this->getJson("/companies/{$company->slug}/ping")->assertForbidden();
});
