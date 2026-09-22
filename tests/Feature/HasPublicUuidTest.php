<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

it('generates a version 7 uuid and keeps an integer key', function (Model $model) {
    expect(Str::isUuid($model->uuid, 7))->toBeTrue()
        ->and($model->getKey())->toBeInt();
})->with([
    'user' => fn () => User::factory()->create(),
    'company' => fn () => Company::factory()->create(),
    'membership created by attach' => function () {
        app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');

        return CompanyUser::sole();
    },
]);

it('binds a membership of the current company by uuid', function () {
    registerMembershipRoute();
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');
    $membership = CompanyUser::sole();

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/members/{$membership->uuid}")
        ->assertOk()
        ->assertJsonPath('member_uuid', $membership->uuid);
});

it('does not bind a membership of another company', function () {
    registerMembershipRoute();
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');
    $other = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Other');
    $foreignMembership = CompanyUser::where('company_id', $other->id)->sole();

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/members/{$foreignMembership->uuid}")
        ->assertNotFound();
});
