<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «Remember me» token housekeeping (ADR-010).
 *
 * Ending other sessions is worthless if their «remember me» cookie can open a
 * new one a minute later, so every revocation rotates the account's token
 * (contract §2.2). The device that asked for the revocation keeps its own
 * cookie: when it had one, it is re-issued with the new token.
 */
class RememberTokens
{
    /**
     * Rotate $user's token; re-issue this device's cookie when $request had
     * one and the device stays signed in.
     */
    public function rotate(User $user, ?Request $keepDevice = null): void
    {
        $guard = Auth::guard('web');
        $token = Str::random(60);

        $user->setRememberToken($token);
        DB::table($user->getTable())->where($user->getKeyName(), $user->getKey())->update(['remember_token' => $token]);

        if ($keepDevice === null || ! $guard instanceof SessionGuard || ! $keepDevice->cookies->has($guard->getRecallerName())) {
            return;
        }

        // The same value SessionGuard::queueRecallerCookie writes.
        Cookie::queue(Cookie::make(
            $guard->getRecallerName(),
            $user->getAuthIdentifier().'|'.$token.'|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
            // SessionGuard keeps its duration protected; it comes from here.
            (int) config('auth.guards.web.remember', 576000),
        ));
    }

    /**
     * Invalidate every «remember me» cookie in existence (nexo:rotate-remember-tokens).
     * A null token matches no cookie; the next «remember me» login sets a new
     * one. Returns how many accounts had a token. Idempotent.
     */
    public function invalidateAll(): int
    {
        return DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }
}
