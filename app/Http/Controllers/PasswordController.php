<?php

namespace App\Http\Controllers;

use App\Actions\ChangeUserPassword;
use App\Auth\IdpSessions;
use App\Auth\RememberTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Change the password, gated by the current password (AC-PROFILE-2). The
     * shared action notifies the account and revokes the user's other sessions
     * while keeping this one (AC-PWD-6).
     */
    public function update(Request $request, ChangeUserPassword $changePassword, IdpSessions $sessions, RememberTokens $rememberTokens): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(config('nexo.password.min_length'))],
        ]);

        $user = $request->user();

        $changePassword($user, $validated['password'], $request->session()->getId(), $sessions->current($request->session()));

        // The action rotated the «remember me» token (and the password hash the
        // cookie is bound to): re-issue this device's cookie if it had one, so
        // changing the password does not sign out the device that changed it.
        $rememberTokens->rotate($user, keepDevice: $request);

        return back()->with('status', __('Your password was changed.'));
    }
}
