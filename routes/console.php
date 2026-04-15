<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PDPA data minimization — auto-delete autonap_records older than 90 days, daily at 03:00 Asia/Bangkok
Schedule::command('autonap:prune --days=90')
    ->dailyAt('03:00')
    ->timezone('Asia/Bangkok')
    ->onOneServer()
    ->runInBackground();
