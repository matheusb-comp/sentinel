<?php

use App\Database\Partitioning\PartitionInterval;
use App\Models\Company;
use App\Models\Concerns\HasIsoTimestamps;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToPrimaryModel;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

it('declares how every model relates to the tenant', function () {
    // Adding a model here is a decision, not bookkeeping: it means its rows are
    // shared across every company.
    $central = [
        User::class,
        Company::class,
    ];

    $models = modelClasses();

    expect($models)->not->toBeEmpty();

    // BelongsToTenant carries the tenant key. BelongsToPrimaryModel only adds
    // whereHas() on a parent, so it scopes nothing unless that parent is itself
    // scoped; the chain is walked until it reaches a model that carries the key.
    $reachesTenant = function (string $model) use (&$reachesTenant): bool {
        $traits = class_uses_recursive($model);

        if (in_array(BelongsToTenant::class, $traits, true)) {
            return true;
        }

        if (! in_array(BelongsToPrimaryModel::class, $traits, true)) {
            return false;
        }

        $instance = new $model;
        $parent = $instance->{$instance->getRelationshipToPrimaryModel()}()->getRelated();

        return $reachesTenant($parent::class);
    };

    foreach ($models as $model) {
        expect($reachesTenant($model) || in_array($model, $central, true))->toBeTrue(
            "{$model} must reach the tenant, directly or through a parent, or be listed as central"
        );
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
    $models = modelClasses();

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        expect(in_array(HasPublicUuid::class, class_uses_recursive($model), true))
            ->toBeTrue("{$model} must use HasPublicUuid");
    }
});

it('hides every column that holds a secret', function () {
    // Named rather than listed, so the guard is already armed when a column
    // nobody has thought of yet arrives.
    $secretish = ['password', 'secret', 'token', 'hash'];

    $models = modelClasses();

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        $instance = new $model;

        $secrets = collect(Schema::getColumnListing($instance->getTable()))
            ->filter(fn (string $column): bool => Str::contains($column, $secretish, ignoreCase: true));

        foreach ($secrets as $secret) {
            expect(in_array($secret, $instance->getHidden(), true))
                ->toBeTrue("{$model} must hide {$secret}");
        }
    }
});

it('casts every date column to the shared format', function () {
    $models = modelClasses();

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        if (! in_array(HasIsoTimestamps::class, class_uses_recursive($model), true)) {
            continue;
        }

        $instance = new $model;

        foreach ($instance->getDates() as $date) {
            expect($instance->getCasts()[$date] ?? null)
                ->toBe('datetime:c', "{$model} must cast {$date} with isoCasts()");
        }
    }
});

it('hides every numeric key column from serialization', function () {
    $models = modelClasses();

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

it('names every series table so the statements can use it unquoted', function () {
    foreach (config('series.tables') as $table => $settings) {
        $partition = $table.'_p'.PartitionInterval::from($settings['partition'])->suffix(CarbonImmutable::now('UTC'));

        expect($table)->toMatch('/^[a-z_][a-z0-9_]*$/', "{$table} must be lowercase, without a dot")
            ->and(strlen($partition))->toBeLessThanOrEqual(63, "{$partition} is longer than Postgres keeps");
    }
});
