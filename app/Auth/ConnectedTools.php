<?php

namespace App\Auth;

use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «Herramientas conectadas» (H-nexoid-03): the tools this account has signed
 * in to, when it last did, and where «Abrir» goes.
 *
 * Two sources, because each one forgets something:
 *   - idp_session_clients: every authorization code since session control
 *     (ADR-010), with its last use — pruned with its sid a while after the
 *     session ends;
 *   - oauth_access_tokens: every token Passport ever issued to the account,
 *     including the ones from before session control.
 * Revoked clients are left out: the account cannot use them any more.
 *
 * A client is named after the ecosystem registry (config/nexo-ecosystem.php)
 * when one of its redirect URIs lives on a registered tool's origin, so the
 * list reads «Nexo Events», not whatever the operator typed when registering
 * it, and «Abrir» is that tool's url + entry (its panel, signed in through
 * Nexo ID with zero clicks). Any other client keeps its own name and has no
 * «Abrir»: Nexo ID does not know where its front door is.
 */
class ConnectedTools
{
    /** @return Collection<int, ConnectedTool> most recently used first */
    public function forUser(User $user): Collection
    {
        $lastUse = [];

        $fromSessions = DB::table('idp_session_clients')
            ->join('idp_sessions', 'idp_sessions.sid', '=', 'idp_session_clients.sid')
            ->where('idp_sessions.user_id', $user->getKey())
            ->groupBy('idp_session_clients.client_id')
            ->selectRaw('idp_session_clients.client_id as client_id, max(idp_session_clients.last_seen_at) as last_used')
            ->get();

        $fromTokens = DB::table('oauth_access_tokens')
            ->where('user_id', $user->getKey())
            ->groupBy('client_id')
            ->selectRaw('client_id, max(created_at) as last_used')
            ->get();

        foreach ($fromSessions->concat($fromTokens) as $row) {
            $id = (string) $row->client_id;
            $at = $row->last_used !== null ? Carbon::parse((string) $row->last_used) : null;

            if (! array_key_exists($id, $lastUse) || ($at !== null && ($lastUse[$id] === null || $at->greaterThan($lastUse[$id])))) {
                $lastUse[$id] = $at;
            }
        }

        if ($lastUse === []) {
            return collect();
        }

        /** @var array<string, ConnectedTool> $tools */
        $tools = [];

        foreach (OauthClient::query()->whereKey(array_keys($lastUse))->where('revoked', false)->get() as $client) {
            $described = $this->describe($client);
            $at = $lastUse[(string) $client->getKey()] ?? null;
            $key = $described['key'] ?? 'client:'.$client->getKey();

            // Two clients of one tool (a re-registration) are one row.
            $previous = $tools[$key] ?? null;
            if ($previous !== null && ($at === null || ($previous->lastUsedAt !== null && $previous->lastUsedAt->greaterThanOrEqualTo($at)))) {
                continue;
            }

            $tools[$key] = new ConnectedTool(
                clientId: (string) $client->getKey(),
                name: $described['name'],
                mark: $described['mark'],
                openUrl: $described['openUrl'],
                lastUsedAt: $at,
            );
        }

        return collect(array_values($tools))
            ->sortByDesc(fn (ConnectedTool $tool): int => $tool->lastUsedAt?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * The tools each sid signed in to, by display name.
     *
     * @param  array<int, string>  $sids
     * @return array<string, array<int, string>>
     */
    public function namesBySid(array $sids): array
    {
        $sids = array_values(array_filter($sids, fn (string $sid): bool => $sid !== ''));

        if ($sids === []) {
            return [];
        }

        $rows = DB::table('idp_session_clients')->whereIn('sid', $sids)->get(['sid', 'client_id']);
        $clients = OauthClient::query()->whereKey($rows->pluck('client_id')->unique()->all())->get()->keyBy(fn (OauthClient $c): string => (string) $c->getKey());

        $names = [];
        foreach ($rows as $row) {
            $client = $clients[(string) $row->client_id] ?? null;
            if ($client !== null) {
                $names[(string) $row->sid][] = $this->describe($client)['name'];
            }
        }

        return array_map(fn (array $list): array => array_values(array_unique($list)), $names);
    }

    /**
     * How a client shows up for a person.
     *
     * @return array{key: string|null, name: string, mark: string|null, openUrl: string|null}
     */
    public function describe(OauthClient $client): array
    {
        $origins = array_map(self::origin(...), (array) $client->getAttribute('redirect_uris'));

        foreach ((array) config('nexo-ecosystem.tools', []) as $key => $tool) {
            if (! is_array($tool) || $key === config('nexo-ecosystem.current')) {
                continue;
            }

            $url = rtrim((string) ($tool['url'] ?? ''), '/');
            if ($url === '' || ! in_array(self::origin($url), $origins, true)) {
                continue;
            }

            $entry = (string) ($tool['entry'] ?? '');

            return [
                'key' => (string) $key,
                'name' => (string) ($tool['name'] ?? $client->getAttribute('name')),
                'mark' => isset($tool['mark']) ? (string) $tool['mark'] : null,
                'openUrl' => $url.($entry !== '' ? '/'.ltrim($entry, '/') : ''),
            ];
        }

        return ['key' => null, 'name' => (string) $client->getAttribute('name'), 'mark' => null, 'openUrl' => null];
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return strtolower($parts['scheme'].'://'.$parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
