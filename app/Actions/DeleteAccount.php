<?php

namespace App\Actions;

use App\Auth\IdpSessions;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * «Borrar mi cuenta» (H-nexoid-10): the account and everything Nexo ID holds
 * about it, gone, and every tool told to sign it out.
 *
 * The gate of DATABASE-STANDARD §12 #2 («Tablas de Passport sin FKs … GATE:
 * antes de implementar account deletion») is resolved HERE, explicitly, and not
 * by adding foreign keys: Passport's tables keep upstream's shape (no FKs,
 * `user_id` with no constraint), so deleting the user row alone would leave
 * every code, access token and refresh token of the account alive in the
 * database — the refresh tokens hang off access_token_id, not user_id, so not
 * even a user_id cascade would reach them. Each table is cleaned by name, in
 * one transaction (all or nothing: a half-deleted identity is worse than
 * none), and DeleteAccountTest pins that no row of the account survives in any
 * of them.
 *
 * Order matters:
 *   1. IdpSessions::endForAccountDeletion() FIRST, while the sessions and
 *      tokens still say which tools the account used: every sid ends and each
 *      tool gets one sub-only logout_token (CONTRATO-F3 §2.2). It happens
 *      before the transaction on purpose — it waits up to ~2 s on the tools,
 *      and a transaction must not hold its locks across the network. If the
 *      deletion then fails, the tools have only signed the person out, which
 *      is harmless and what they asked for anyway.
 *   2. The transaction: Passport (refresh → access → codes → device codes),
 *      clients the account owns (third-party apps it registered, with every
 *      token issued to them), the browser sessions, the password-reset row
 *      (keyed by email), the IdP sessions (they also cascade, but the gate
 *      asks for nothing implicit) and finally the user.
 *
 * What it does NOT touch: what the person keeps inside each tool. Those are
 * the tools' own data, and the profile page says so before asking.
 */
class DeleteAccount
{
    public function __construct(private IdpSessions $sessions) {}

    public function __invoke(User $user): void
    {
        $this->sessions->endForAccountDeletion($user);

        DB::transaction(function () use ($user): void {
            $userId = (string) $user->getKey();

            // Clients this account registered (owner morph). A first-party
            // tool has no owner and is never touched.
            $ownedClientIds = OauthClient::query()
                ->where('owner_type', $user->getMorphClass())
                ->where('owner_id', $userId)
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            $accessTokenIds = DB::table('oauth_access_tokens')
                ->where('user_id', $userId)
                ->when($ownedClientIds !== [], fn ($query) => $query->orWhereIn('client_id', $ownedClientIds))
                ->pluck('id')
                ->all();

            foreach (array_chunk($accessTokenIds, 500) as $chunk) {
                DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $chunk)->delete();
                DB::table('oauth_access_tokens')->whereIn('id', $chunk)->delete();
            }

            foreach (['oauth_auth_codes', 'oauth_device_codes'] as $table) {
                DB::table($table)
                    ->where('user_id', $userId)
                    ->when($ownedClientIds !== [], fn ($query) => $query->orWhereIn('client_id', $ownedClientIds))
                    ->delete();
            }

            if ($ownedClientIds !== []) {
                DB::table('oauth_clients')->whereIn('id', $ownedClientIds)->delete();
            }

            DB::table((string) config('session.table', 'sessions'))->where('user_id', $userId)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('idp_session_clients')
                ->whereIn('sid', DB::table('idp_sessions')->where('user_id', $userId)->select('sid'))
                ->delete();
            DB::table('idp_sessions')->where('user_id', $userId)->delete();

            $user->delete();
        });
    }
}
