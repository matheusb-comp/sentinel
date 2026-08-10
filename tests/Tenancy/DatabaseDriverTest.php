<?php

use Illuminate\Support\Facades\DB;

it('runs against postgres', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});
