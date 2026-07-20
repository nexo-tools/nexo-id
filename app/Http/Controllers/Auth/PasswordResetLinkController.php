<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset link. The response is identical whether or not the email
     * belongs to an account — no user enumeration (AC-PWD-2).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        return back()->with('status', __('If that email is registered, we sent a password reset link.'));
    }
}
