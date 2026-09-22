<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\CompanyUser;
use App\Models\User;
use App\Rules\ExistsByUuid;
use Illuminate\Support\Facades\Validator;

/**
 * Postgres rejects a non-uuid value for a uuid column, so only here does a
 * missing format check turn a lookup into a 500.
 */
it('returns 404 for a company path that is neither a slug nor a uuid', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/companies/not-a-uuid/ping')
        ->assertNotFound();
});

it('returns 404 for a malformed member uuid', function () {
    registerMembershipRoute();
    $user = User::factory()->create();
    $company = app(CreateCompanyForUser::class)->handle($user, 'Acme');

    $this->actingAs($user)
        ->getJson("/companies/{$company->slug}/members/not-a-uuid")
        ->assertNotFound();
});

it('rejects a malformed reference without querying the uuid column', function () {
    $validator = Validator::make(
        ['member_uuid' => 'not-a-uuid'],
        ['member_uuid' => [new ExistsByUuid(CompanyUser::class)]],
    );

    expect($validator->errors()->first('member_uuid'))->toBe('The member uuid field must be a valid UUID.');
});
