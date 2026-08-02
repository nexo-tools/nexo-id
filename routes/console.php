<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purge revoked/expired OAuth tokens and auth codes nightly (needs the cron).
// Inline (Artisan::call): this hosting disables proc_open/exec, so a scheduled
// subprocess dies before it starts — the nightly purge was silently failing.
Schedule::call(fn () => Artisan::call('passport:purge'))
    ->name('passport-purge')
    ->daily();

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
 * The drain runs INLINE (Schedule::call + Artisan::call), never as a
 * Schedule::command subprocess: proc_open/exec are disabled on this hosting
 * (Hostinger, PHP 8.5 desde 2026-07-27) and a scheduled subprocess dies
 * before it starts.
 *
 * --stop-when-empty exits once the queue drains so runs never pile up;
 * --max-time keeps a run inside its minute; withoutOverlapping is the belt to
 * that braces.
 */
Schedule::call(fn () => Artisan::call('queue:work --stop-when-empty --tries=3 --max-time=55'))
    ->name('queue-drain')
    ->everyMinute()
    ->withoutOverlapping();
