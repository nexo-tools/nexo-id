<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purge revoked/expired OAuth tokens and auth codes nightly (needs the cron).
Schedule::command('passport:purge')->daily();

/*
 * Shared hosting cannot run a long-lived queue worker (no daemons, ADR-002), so
 * the scheduler drains the database queue in short bursts instead. It rides the
 * same cron entry passport:purge already needs (DEPLOYMENT.md § 8):
 *
 *     * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 *
 * Without it the queued PasswordChanged notice never leaves: the row sits in
 * the jobs table while the account page reports the password was changed.
 *
 * --stop-when-empty exits once the queue drains so runs never pile up;
 * --max-time keeps a run inside its minute; withoutOverlapping is the belt to
 * that braces.
 */
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')
    ->everyMinute()
    ->withoutOverlapping();
