<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Every login on the web guard starts an IdP session (sid) — with the
 * password, and also when an expired session is revived by the «remember me»
 * cookie (SessionGuard fires Login for that too). ADR-010, contract §2.1.
 *
 * Registered in AppServiceProvider, deliberately outside app/Listeners so
 * event discovery does not register it a second time.
 */
class StartIdpSessionOnLogin
{
    public function __construct(private IdpSessions $sessions) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard) {
            return;
        }

        $this->sessions->start($guard->getSession(), $event->user);
    }
}
