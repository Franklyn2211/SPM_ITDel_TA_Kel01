<?php

use App\Console\Commands\SyncCisUsers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cis:sync-users', function () {
    $this->call(SyncCisUsers::class);
})->purpose('Sync users from CIS');

# (opsional) jadwalkan
Schedule::command('cis:sync-users')->dailyAt('01:00');
