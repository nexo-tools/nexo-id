<?php

namespace App\Auth;

use App\Models\OauthClient;

/**
 * Which tool a sign-up is for, so the registration screen can say «para entrar
 * a Nexo Links» (CONTRATO-F3 v2.2, «alta que vuelve a la tool»).
 *
 * The answer comes from the URL the person will be sent to after verifying
 * their email (`url.intended`, written only by the server: the guest redirect
 * of an authorize request, or SignUpOnPromptCreate). It names a tool only when
 * that URL is this server's own /oauth/authorize and its client_id +
 * redirect_uri pair is genuine: a registered, non-revoked client with exactly
 * that redirect URI. Anything else — no intended URL, another page, an unknown
 * or revoked client, a redirect_uri the client never registered — names
 * nothing, so a crafted link cannot make the screen vouch for a tool.
 *
 * The tool is named the way «Herramientas conectadas» names it
 * (ConnectedTools::describe): the ecosystem registry's name for a Nexo tool,
 * the OAuth client's own name for anything else.
 */
class SignUpDestination
{
    public function __construct(private ConnectedTools $tools) {}

    public function toolName(mixed $intended): ?string
    {
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $target = parse_url($intended);
        $authorize = parse_url(route('passport.authorizations.authorize'));

        if (! is_array($target) || ! is_array($authorize) || self::origin($target) !== self::origin($authorize)
            || ($target['path'] ?? '') !== ($authorize['path'] ?? '')) {
            return null;
        }

        parse_str((string) ($target['query'] ?? ''), $query);
        $clientId = $query['client_id'] ?? null;
        $redirectUri = $query['redirect_uri'] ?? null;

        if (! is_string($clientId) || $clientId === '' || ! is_string($redirectUri) || $redirectUri === '') {
            return null;
        }

        $client = OauthClient::query()->find($clientId);

        if ($client === null || $client->revoked || ! in_array($redirectUri, (array) $client->redirect_uris, true)) {
            return null;
        }

        return $this->tools->describe($client)['name'];
    }

    /** @param  array<string, int|string>  $parts */
    private static function origin(array $parts): string
    {
        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '')).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
