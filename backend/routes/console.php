<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ✅ Watcher: scan automatique du dossier CV
Schedule::command('cvs:scan')
    ->everyMinute()          // ou ->everyFiveMinutes()
    ->withoutOverlapping();
