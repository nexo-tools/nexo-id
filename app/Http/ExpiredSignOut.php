<?php

namespace App\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * A sign-out form posted with an expired page (CONTRATO-F3 §0.1, v2.1 item 2).
 *
 * A 419 there is a person who wants out and an old CSRF token, not an attack —
 * and a forged cross-site POST is exactly what this must not honour, so it
 * never signs anybody out by itself. It answers with a REDIRECT to a GET page,
 * never with a page rendered here: a 419 is thrown by the CSRF middleware,
 * before SetLocale, SecurityHeaders and the no-store middlewares of the `web`
 * group run, so a page rendered from the exception came out in the default
 * language, without the CSP and cacheable — with the account's name and email
 * in its header. The GET goes through the whole group.
 *
 *  - POST /logout: signed in (the «remember me» cookie still holds) → the
 *    «¿Cerrar sesión en todo Nexo?» confirmation of end_session (GET
 *    /oauth/logout, no hint). Signed out already → the signed-out page.
 *  - POST /oauth/logout (that confirmation, left open until it expired) → the
 *    same confirmation again, with the tool's client_id + return URI + state,
 *    which the GET validates once more (never trusted from the form).
 *  - POST /logout/everywhere → back to the account, where the button is:
 *    confirming «this device» instead would leave the others signed in.
 *
 * Any other form keeps its plain 419.
 */
final class ExpiredSignOut
{
    public static function render(Throwable $e, Request $request): ?RedirectResponse
    {
        $mismatch = $e instanceof TokenMismatchException || $e->getPrevious() instanceof TokenMismatchException;

        if (! $mismatch || ! $request->isMethod('POST')) {
            return null;
        }

        if ($request->routeIs('logout')) {
            return Auth::guard('web')->check()
                ? redirect()->route('openid.end_session_endpoint')
                : redirect()->route('signed-out');
        }

        if ($request->routeIs('openid.end_session.confirm')) {
            $return = [];
            foreach (['client_id', 'post_logout_redirect_uri', 'state'] as $field) {
                $value = $request->input($field);
                if (is_string($value) && $value !== '') {
                    $return[$field] = $value;
                }
            }

            return redirect()->route('openid.end_session_endpoint', $return);
        }

        if ($request->routeIs('logout.everywhere')) {
            return redirect()->route('profile.show')->with('status', __('The page expired. Try again.'));
        }

        return null;
    }
}
