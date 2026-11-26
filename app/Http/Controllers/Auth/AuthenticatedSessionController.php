<?php

namespace App\Http\Controllers\Auth;

use App\Auth\IdpSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Prevent session fixation: issue a fresh session id post-login (AC-LOGIN-4).
        $request->session()->regenerate();

        return redirect()->intended(route('profile.show'));
    }

    /**
     * Sign out of Nexo ID on this device: ends this device's IdP session, and
     * with it the tools that signed in through it (back-channel, ADR-010).
     */
    public function destroy(Request $request, IdpSessions $sessions): RedirectResponse
    {
        $sessions->end([$sessions->current($request->session())], 'logout');

        Auth::guard('web')->logout();

        // Invalidate the session and rotate the CSRF token on logout (AC-LOGIN-5).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
