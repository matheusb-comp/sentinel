<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No overlap, because creating a partition takes an ACCESS EXCLUSIVE lock. The
// mutex expires in 10 minutes, so a run killed halfway does not hold off the next.
Schedule::command('series:maintain-partitions')->daily()->withoutOverlapping(10);
