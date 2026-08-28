<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tool-events:prune')->daily();
Schedule::command('copy-events:prune')->daily();
Schedule::command('tool-tmp:prune')->hourly();
