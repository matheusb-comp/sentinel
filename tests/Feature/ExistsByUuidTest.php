<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Models\CompanyUser;
use App\Models\Device;
use App\Models\User;
use App\Rules\ExistsByUuid;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

it('accepts a membership of the current company', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');
    $membership = CompanyUser::sole();

    tenancy()->initialize($company);

    $validator = Validator::make(
        ['member_uuid' => $membership->uuid],
        ['member_uuid' => [new ExistsByUuid(CompanyUser::class)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects a membership of another company', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Acme');
    $other = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'Other');
    $foreignMembership = CompanyUser::where('company_id', $other->id)->sole();

    tenancy()->initialize($company);

    $validator = Validator::make(
        ['member_uuid' => $foreignMembership->uuid],
        ['member_uuid' => [new ExistsByUuid(CompanyUser::class)]],
    );

    expect($validator->errors()->first('member_uuid'))->toBe('The selected member uuid is invalid.');
});

it('rejects an unknown uuid', function () {
    $validator = Validator::make(
        ['member_uuid' => (string) Str::uuid7()],
        ['member_uuid' => [new ExistsByUuid(CompanyUser::class)]],
    );

    expect($validator->errors()->first('member_uuid'))->toBe('The selected member uuid is invalid.');
});

it('rejects a value that is not a uuid', function () {
    $validator = Validator::make(
        ['member_uuid' => 'not-a-uuid'],
        ['member_uuid' => [new ExistsByUuid(CompanyUser::class)]],
    );

    expect($validator->errors()->first('member_uuid'))->toBe('The member uuid field must be a valid UUID.');
});

it('counts only what its constraints leave in', function () {
    $active = Device::factory()->create();
    $archived = Device::factory()->create();
    Device::whereKey($archived->id)->update(['archived_at' => now()]);

    $rule = fn (): array => [(new ExistsByUuid(Device::class))->whereNull('archived_at')];

    expect(Validator::make(['device_uuid' => $active->uuid], ['device_uuid' => $rule()])->passes())->toBeTrue()
        ->and(Validator::make(['device_uuid' => $archived->uuid], ['device_uuid' => $rule()])->passes())->toBeFalse();
});
