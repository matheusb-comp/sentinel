<?php

use App\Models\Company;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

it('declares every model as either central or tenant scoped', function () {
    // Adding a model here is a decision, not bookkeeping: it means its rows are
    // shared across every company.
    $central = [
        User::class,
        Company::class,
    ];

    $models = collect(File::files(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension());

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        $isTenantScoped = in_array(BelongsToTenant::class, class_uses_recursive($model), true);

        expect($isTenantScoped || in_array($model, $central, true))
            ->toBeTrue("{$model} must use BelongsToTenant or be listed as central");
    }
});

it('exposes a handle method on every action', function () {
    $actions = collect(File::files(app_path('Actions')))
        ->map(fn ($file) => 'App\\Actions\\'.$file->getFilenameWithoutExtension());

    expect($actions)->not->toBeEmpty();

    foreach ($actions as $action) {
        expect(method_exists($action, 'handle'))->toBeTrue("{$action} must expose handle()");
    }
});

it('has no services directory', function () {
    // Business orchestration belongs in app/Actions. See .ai/rules/app.md.
    expect(is_dir(app_path('Services')))->toBeFalse();
});

it('gives every model a public uuid', function () {
    $models = collect(File::files(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension());

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        expect(in_array(HasPublicUuid::class, class_uses_recursive($model), true))
            ->toBeTrue("{$model} must use HasPublicUuid");
    }
});

it('hides every numeric key column from serialization', function () {
    $models = collect(File::files(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension());

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        $instance = new $model;

        $keys = collect(Schema::getColumnListing($instance->getTable()))
            ->filter(fn (string $column): bool => $column === 'id' || str_ends_with($column, '_id'));

        foreach ($keys as $key) {
            expect(in_array($key, $instance->getHidden(), true))
                ->toBeTrue("{$model} must hide {$key}");
        }
    }
});
