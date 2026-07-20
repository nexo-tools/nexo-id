<?php

namespace App\Http\Controllers;

use App\Actions\ChangeUserPassword;
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
    public function update(Request $request, ChangeUserPassword $changePassword): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(config('nexo.password.min_length'))],
        ]);

        $changePassword($request->user(), $validated['password'], $request->session()->getId());

        return back()->with('status', __('Your password was changed.'));
    }
}
