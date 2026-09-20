<?php

use App\Database\Partitioning\PartitionInterval;
use Carbon\CarbonImmutable;

it('starts a daily range at midnight', function () {
    $start = PartitionInterval::Day->start(CarbonImmutable::parse('2026-09-20 13:47:12', 'UTC'));

    expect($start->toIso8601String())->toBe('2026-09-20T00:00:00+00:00');
});

it('starts an hourly range at the top of the hour', function () {
    $start = PartitionInterval::Hour->start(CarbonImmutable::parse('2026-09-20 13:47:12', 'UTC'));

    expect($start->toIso8601String())->toBe('2026-09-20T13:00:00+00:00');
});

it('starts a weekly range on monday', function () {
    $start = PartitionInterval::Week->start(CarbonImmutable::parse('2026-09-20 13:47:12', 'UTC'));

    expect($start->toIso8601String())->toBe('2026-09-14T00:00:00+00:00');
});

it('advances a monthly range by one calendar month', function () {
    $next = PartitionInterval::Month->next(CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'));

    expect($next->toDateString())->toBe('2026-02-01');
});

it('names a daily partition after the date it starts', function () {
    $suffix = PartitionInterval::Day->suffix(CarbonImmutable::parse('2026-09-20 00:00:00', 'UTC'));

    expect($suffix)->toBe('20260920');
});

it('names an hourly partition with date and time', function () {
    $suffix = PartitionInterval::Hour->suffix(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'));

    expect($suffix)->toBe('20260920_130000');
});

it('reads back the start it wrote into a name', function () {
    $start = CarbonImmutable::parse('2026-09-20 00:00:00', 'UTC');

    $parsed = PartitionInterval::Day->parse(PartitionInterval::Day->suffix($start));

    expect($parsed->equalTo($start))->toBeTrue();
});

it('returns null for a suffix outside its format', function () {
    expect(PartitionInterval::Day->parse('default'))->toBeNull()
        ->and(PartitionInterval::Day->parse('20260920_130000'))->toBeNull();
});
