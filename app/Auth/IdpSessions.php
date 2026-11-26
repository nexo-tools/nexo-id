<?php

namespace App\Auth;

use App\Auth\Backchannel\BackchannelDelivery;
use App\Auth\Backchannel\BackchannelLogoutDispatcher;
use App\Models\IdpSession;
use App\Models\IdpSessionClient;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The identity provider's session registry (ADR-010): Nexo ID owns the session
 * of the whole ecosystem through it.
 *
 * - A login (password or «remember me» cookie) starts a `sid`, kept in the
 *   Laravel session under `nexo_idp.sid` and in `idp_sessions`. It survives
 *   `session()->regenerate()` because it lives in the session data, not in the
 *   session id.
 * - Every authorization code issued during that login registers its client in
 *   `idp_session_clients` and carries the sid into the id_token.
 * - Ending a sid (logout, revocation, password change, account deletion…)
 *   marks it ended and hands one back-channel delivery per registered client
 *   to the dispatcher.
 */
class IdpSessions
{
    /** Session key that holds the current device's sid. */
    public const SESSION_KEY = 'nexo_idp.sid';

    public function __construct(private BackchannelLogoutDispatcher $dispatcher) {}

    /**
     * Start a fresh sid for a login on this device (the Login event).
     */
    public function start(Session $session, User $user): string
    {
        $sid = Str::random(40);

        IdpSession::query()->create([
            'sid' => $sid,
            'user_id' => $user->getKey(),
            'last_seen_at' => now(),
        ]);

        $session->put(self::SESSION_KEY, $sid);

        return $sid;
    }

    /**
     * The sid of this device's session, or null when it has none (a session
     * opened before the deploy, or a guest).
     */
    public function current(Session $session): ?string
    {
        $sid = $session->get(self::SESSION_KEY);

        return is_string($sid) && $sid !== '' ? $sid : null;
    }

    /**
     * The live sid of this device for $user, creating one lazily when the
     * session has none or holds one that is unusable (ended, someone else's,
     * or gone). Called on authorize, where a sid is required.
     */
    public function ensure(Session $session, User $user): string
    {
        $sid = $this->current($session);

        if ($sid !== null) {
            $row = IdpSession::query()->find($sid);

            if ($row !== null && ! $row->isEnded() && $row->user_id === $user->getKey()) {
                return $sid;
            }
        }

        return $this->start($session, $user);
    }

    /**
     * Whether a sid has been ended. Unknown sids are not "ended": a missing row
     * (pruned long after it went stale) must not sign anybody out.
     */
    public function isEnded(string $sid): bool
    {
        return IdpSession::query()->whereKey($sid)->whereNotNull('ended_at')->exists();
    }

    /**
     * Record that $clientId obtained an authorization code during $sid.
     */
    public function recordClient(string $sid, string $clientId): void
    {
        $now = now();

        DB::table('idp_session_clients')->upsert(
            [['sid' => $sid, 'client_id' => $clientId, 'created_at' => $now, 'last_seen_at' => $now]],
            ['sid', 'client_id'],
            ['last_seen_at'],
        );

        IdpSession::query()->whereKey($sid)->update(['last_seen_at' => $now]);
    }

    /**
     * End the given sids and tell every tool that had a code in them. Sids that
     * were already ended (or never existed) are skipped, so ending twice never
     * notifies twice.
     *
     * @param  array<int, string|null>  $sids
     */
    public function end(array $sids, string $reason): void
    {
        $sids = array_values(array_unique(array_filter($sids, fn (?string $sid): bool => is_string($sid) && $sid !== '')));

        if ($sids === []) {
            return;
        }

        $open = IdpSession::query()->whereKey($sids)->whereNull('ended_at')->get(['sid', 'user_id']);

        if ($open->isEmpty()) {
            return;
        }

        IdpSession::query()
            ->whereKey($open->pluck('sid')->all())
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);

        $this->dispatcher->dispatch($this->deliveriesFor($open));
    }

    /**
     * End every open sid of $user except $keepSid (null ends them all).
     */
    public function endAllForUser(User $user, string $reason, ?string $keepSid = null): void
    {
        $sids = IdpSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('ended_at')
            ->when($keepSid !== null, fn ($query) => $query->where('sid', '!=', $keepSid))
            ->pluck('sid')
            ->all();

        $this->end($sids, $reason);
    }

    /**
     * Account deletion (§2.2): end every sid, and send each client the account
     * ever used a `sub`-only logout_token, so the tool kills every session of
     * that user — including ones whose sid this registry no longer knows.
     *
     * MUST run before the user row is deleted: idp_sessions cascades on it,
     * and the client list comes from there and from Passport's token table.
     */
    public function endForAccountDeletion(User $user): void
    {
        $this->endAllForUser($user, 'account_deleted');

        $clientIds = IdpSessionClient::query()
            ->join('idp_sessions', 'idp_sessions.sid', '=', 'idp_session_clients.sid')
            ->where('idp_sessions.user_id', $user->getKey())
            ->pluck('idp_session_clients.client_id')
            ->merge(DB::table('oauth_access_tokens')->where('user_id', $user->getKey())->pluck('client_id'))
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $deliveries = DB::table('oauth_clients')
            ->whereIn('id', $clientIds)
            ->whereNotNull('backchannel_logout_uri')
            ->where('revoked', false)
            ->get(['id', 'backchannel_logout_uri'])
            ->map(fn (object $client): BackchannelDelivery => new BackchannelDelivery(
                clientId: (string) $client->id,
                uri: (string) $client->backchannel_logout_uri,
                sub: (string) $user->getKey(),
                sid: null,
            ));

        $this->dispatcher->dispatch($deliveries);
    }

    /**
     * One delivery per (client with a back-channel URI, sid).
     *
     * @param  Collection<int, IdpSession>  $sessions
     * @return Collection<int, BackchannelDelivery>
     */
    private function deliveriesFor(Collection $sessions): Collection
    {
        $subs = $sessions->pluck('user_id', 'sid');

        return DB::table('idp_session_clients')
            ->join('oauth_clients', 'oauth_clients.id', '=', 'idp_session_clients.client_id')
            ->whereIn('idp_session_clients.sid', $subs->keys()->all())
            ->whereNotNull('oauth_clients.backchannel_logout_uri')
            ->where('oauth_clients.revoked', false)
            ->get(['idp_session_clients.sid', 'oauth_clients.id', 'oauth_clients.backchannel_logout_uri'])
            ->map(fn (object $row): BackchannelDelivery => new BackchannelDelivery(
                clientId: (string) $row->id,
                uri: (string) $row->backchannel_logout_uri,
                sub: (string) $subs[$row->sid],
                sid: (string) $row->sid,
            ))
            ->values();
    }
}
