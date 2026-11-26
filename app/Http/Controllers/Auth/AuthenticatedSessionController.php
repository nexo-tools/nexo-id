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
     *
     * Reachable without a session on purpose: «Cerrar sesión» on a page whose
     * session already expired used to bounce to /login, as if signing out had
     * failed. With nothing left to end it lands on the same signed-out page.
     */
    public function destroy(Request $request, IdpSessions $sessions): RedirectResponse
    {
        $sessions->end([$sessions->current($request->session())], 'logout');

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        // Invalidate the session and rotate the CSRF token on logout (AC-LOGIN-5).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // «Cerraste sesión en todo Nexo», with the way back in (item 9 of F3).
        return redirect()->route('signed-out');
    }
}
