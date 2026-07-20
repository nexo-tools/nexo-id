<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ChangeUserPassword;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Reset the password with a single-use, expiring token (AC-PWD-3, AC-PWD-4).
     * The shared action notifies the account and revokes other sessions (AC-PWD-6).
     */
    public function store(Request $request, ChangeUserPassword $changePassword): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(config('nexo.password.min_length'))],
        ]);

        $status = Password::reset(
            [
                'email' => strtolower(trim((string) $request->input('email'))),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user) use ($request, $changePassword): void {
                $changePassword($user, (string) $request->input('password'));
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
