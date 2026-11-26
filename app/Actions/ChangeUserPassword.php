<?php

namespace App\Actions;

use App\Auth\IdpSessions;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChangeUserPassword
{
    public function __construct(private IdpSessions $sessions) {}

    /**
     * Set a new password, notify the account, and invalidate the user's other
     * sessions (AC-PWD-6, reused by the profile password change AC-PROFILE-2).
     * The IdP sessions (sids) of those devices end too, which signs them out
     * of every tool through the back-channel (ADR-010).
     *
     * @param  string|null  $keepSessionId  session id to preserve (the current
     *                                      one on a logged-in change); null
     *                                      revokes every session (reset flow).
     * @param  string|null  $keepSid  IdP session to preserve (the current
     *                                device's sid on a logged-in change).
     */
    public function __invoke(User $user, string $newPassword, ?string $keepSessionId = null, ?string $keepSid = null): void
    {
        // The 'hashed' cast hashes the plaintext on save.
        $user->password = $newPassword;
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->sessions->endAllForUser(
            $user,
            $keepSessionId === null ? 'password_reset' : 'password_changed',
            keepSid: $keepSessionId === null ? null : $keepSid,
        );

        $this->invalidateOtherSessions($user, $keepSessionId);

        $user->notify((new PasswordChanged)->locale(app()->getLocale()));
    }

    private function invalidateOtherSessions(User $user, ?string $keepSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
