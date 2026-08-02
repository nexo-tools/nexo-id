<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChangeUserPassword
{
    /**
     * Set a new password, notify the account, and invalidate the user's other
     * sessions (AC-PWD-6, reused by the profile password change AC-PROFILE-2).
     *
     * @param  string|null  $keepSessionId  session id to preserve (the current
     *                                      one on a logged-in change); null
     *                                      revokes every session (reset flow).
     */
    public function __invoke(User $user, string $newPassword, ?string $keepSessionId = null): void
    {
        // The 'hashed' cast hashes the plaintext on save.
        $user->password = $newPassword;
        $user->setRememberToken(Str::random(60));
        $user->save();

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
