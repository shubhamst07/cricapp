<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule continuous background syncing of live cricket matches, scorecards, commentary, and overs
Schedule::command('cricket:sync-live')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();

