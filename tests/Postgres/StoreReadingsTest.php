<?php

use App\Actions\Companies\CreateCompanyForUser;
use App\Actions\Readings\StoreReadings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Readings of one sensor, a minute apart, in the format the action expects.
 *
 * @return list<array{sensor_id: int, time: string, value: float}>
 */
function readingsAt(int $sensorId, CarbonImmutable $start, float ...$values): array
{
    $readings = [];

    foreach ($values as $i => $value) {
        $readings[] = [
            'sensor_id' => $sensorId,
            'time' => $start->addMinutes($i)->format('Y-m-d H:i:s.uP'),
            'value' => $value,
        ];
    }

    return $readings;
}

it('writes the readings and both rollup levels', function () {
    $sensor = seedSeriesSensor();
    $start = CarbonImmutable::now('UTC')->startOfHour();

    $count = app(StoreReadings::class)->handle(readingsAt($sensor->id, $start, 10.0, 20.0, 30.0));

    $bucket = DB::table('readings_5m')->where('sensor_id', $sensor->id)->first();

    expect($count)->toBe(3)
        ->and(DB::table('readings')->where('sensor_id', $sensor->id)->count())->toBe(3)
        ->and((float) $bucket->value_min)->toBe(10.0)
        ->and((float) $bucket->value_max)->toBe(30.0)
        ->and((float) $bucket->value_sum)->toBe(60.0)
        ->and((int) $bucket->sample_count)->toBe(3)
        ->and(DB::table('readings_1h')->where('sensor_id', $sensor->id)->count())->toBe(1);
});

it('folds one batch into a bucket of each width', function () {
    $sensor = seedSeriesSensor();
    $start = CarbonImmutable::now('UTC')->startOfHour();

    app(StoreReadings::class)->handle([
        ...readingsAt($sensor->id, $start, 10.0),
        ...readingsAt($sensor->id, $start->addMinutes(6), 30.0),
    ]);

    expect(DB::table('readings_5m')->where('sensor_id', $sensor->id)->count())->toBe(2)
        ->and(DB::table('readings_1h')->where('sensor_id', $sensor->id)->count())->toBe(1)
        ->and((int) DB::table('readings_1h')->where('sensor_id', $sensor->id)->value('sample_count'))
        ->toBe(2);
});

it('leaves the rollup unchanged when the same batch arrives again', function () {
    $sensor = seedSeriesSensor();
    $start = CarbonImmutable::now('UTC')->startOfHour();
    $batch = readingsAt($sensor->id, $start, 10.0, 20.0);

    app(StoreReadings::class)->handle($batch);
    $again = app(StoreReadings::class)->handle($batch);

    $bucket = DB::table('readings_5m')->where('sensor_id', $sensor->id)->first();

    expect($again)->toBe(0)
        ->and((float) $bucket->value_sum)->toBe(30.0)
        ->and((int) $bucket->sample_count)->toBe(2);
});

it('accumulates a later batch into the bucket already there', function () {
    $sensor = seedSeriesSensor();
    $start = CarbonImmutable::now('UTC')->startOfHour();

    app(StoreReadings::class)->handle(readingsAt($sensor->id, $start, 10.0));
    app(StoreReadings::class)->handle(readingsAt($sensor->id, $start->addMinutes(1), 30.0));

    $bucket = DB::table('readings_5m')->where('sensor_id', $sensor->id)->first();

    expect((float) $bucket->value_min)->toBe(10.0)
        ->and((float) $bucket->value_max)->toBe(30.0)
        ->and((float) $bucket->value_sum)->toBe(40.0)
        ->and((int) $bucket->sample_count)->toBe(2);
});

it('counts a reading repeated inside one batch only once', function () {
    $sensor = seedSeriesSensor();
    $one = readingsAt($sensor->id, CarbonImmutable::now('UTC')->startOfHour(), 10.0);

    $count = app(StoreReadings::class)->handle([...$one, ...$one]);

    expect($count)->toBe(1)
        ->and((int) DB::table('readings_5m')->where('sensor_id', $sensor->id)->value('sample_count'))
        ->toBe(1);
});

it('keeps the sub-second part of the instant it was given', function () {
    $sensor = seedSeriesSensor();
    $start = CarbonImmutable::now('UTC')->startOfHour();

    app(StoreReadings::class)->handle([
        ['sensor_id' => $sensor->id, 'time' => $start->format('Y-m-d H:i:s.uP'), 'value' => 1.0],
        ['sensor_id' => $sensor->id, 'time' => $start->addMilliseconds(400)->format('Y-m-d H:i:s.uP'), 'value' => 2.0],
    ]);

    expect(DB::table('readings')->where('sensor_id', $sensor->id)->count())->toBe(2);
});

it('touches nothing for an empty batch', function () {
    expect(app(StoreReadings::class)->handle([]))->toBe(0)
        ->and(DB::table('readings')->count())->toBe(0);
});

it('writes through the connection that is current when it runs', function () {
    $company = app(CreateCompanyForUser::class)->handle(User::factory()->create(), 'A');
    $foreign = seedSeriesSensor();
    $action = app(StoreReadings::class);

    tenancy()->initialize($company);

    $store = fn () => $action->handle(readingsAt($foreign->id, CarbonImmutable::now('UTC')->startOfHour(), 1.0));

    expect($store)->toThrow(QueryException::class, 'row-level security');

    tenancy()->end();
});

it('stores every significant digit of a value', function () {
    $sensor = seedSeriesSensor();
    $start = CarbonImmutable::now('UTC')->startOfHour();

    app(StoreReadings::class)->handle(readingsAt($sensor->id, $start, 3.141592653589793, 9007199254740991.0));

    expect(DB::table('readings')->where('sensor_id', $sensor->id)->orderBy('time')->pluck('value')->all())
        ->toBe([3.141592653589793, 9007199254740991.0]);
});
