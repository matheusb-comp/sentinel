<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No overlap, because creating a partition takes an ACCESS EXCLUSIVE lock.
Schedule::command('partitions:maintain')->hourly()->withoutOverlapping();
