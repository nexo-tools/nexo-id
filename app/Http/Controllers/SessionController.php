<?php

namespace App\Http\Controllers;

use App\Auth\IdpSessions;
use App\Auth\RememberTokens;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The account's own session controls (AC-SESS-2/3, ADR-010). Each one ends the
 * IdP sessions (sids) involved — which signs the affected devices out of every
 * tool too, through the back-channel — and rotates the «remember me» token so
 * no cookie can bring a revoked device back.
 */
class SessionController extends Controller
{
    public function __construct(
        private IdpSessions $sessions,
        private RememberTokens $rememberTokens,
    ) {}

    /**
     * «Cerrar esta sesión»: one specific other session of the current user.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $row = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', $id)
            ->where('id', '!=', $request->session()->getId())
            ->first(['id', 'payload']);

        if ($row !== null) {
            $this->sessions->end([self::sidFromPayload((string) $row->payload)], 'session_revoked');

            DB::table(config('session.table', 'sessions'))->where('id', $row->id)->delete();

            $this->rememberTokens->rotate($user, keepDevice: $request);
        }

        return back()->with('status', __('That session was signed out.'));
    }

    /**
     * «Cerrar las demás sesiones»: every other session, keeping this one.
     */
    public function destroyOthers(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->sessions->endAllForUser($user, 'other_sessions_revoked', keepSid: $this->sessions->current($request->session()));

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $this->rememberTokens->rotate($user, keepDevice: $request);

        return back()->with('status', __('All other sessions were signed out.'));
    }

    /**
     * «Cerrar sesión en todos los dispositivos» (D3): every session, this one
     * included.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->sessions->endAllForUser($user, 'all_devices');

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();

        // logout() rotates the remember token itself (SessionGuard::logout).
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('You signed out on every device.'));
    }

    /**
     * The sid stored in a database session row, or null (a session opened
     * before session control, or a payload this code cannot read). The
     * payload is what DatabaseSessionHandler wrote: base64 of the serialized
     * session — json by default (config session.serialization), encrypted
     * first when session.encrypt is on.
     */
    public static function sidFromPayload(string $payload): ?string
    {
        try {
            $data = (string) base64_decode($payload, true);

            if (config('session.encrypt')) {
                $data = (string) decrypt($data, false);
            }

            $attributes = config('session.serialization', 'php') === 'json'
                ? json_decode($data, true)
                : @unserialize($data, ['allowed_classes' => false]);
        } catch (Throwable) {
            return null;
        }

        $sid = is_array($attributes) ? ($attributes['nexo_idp']['sid'] ?? null) : null;

        return is_string($sid) && $sid !== '' ? $sid : null;
    }
}
