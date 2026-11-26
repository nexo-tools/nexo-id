<?php

namespace App\Oidc;

use App\Auth\IdpSessions;
use App\Models\User;
use DateInterval;
use Illuminate\Support\Facades\Auth;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use OpenIDConnect\Grant\AuthCodeGrant as BridgeAuthCodeGrant;
use OpenIDConnect\Interfaces\CurrentRequestServiceInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The bridge's auth-code grant (which already threads `nonce` through the
 * encrypted code payload) plus the IdP session (ADR-010): when a code is
 * issued it
 *
 * 1. resolves this device's `sid` — lazily creating one for a session opened
 *    before session control existed;
 * 2. registers the client against that sid (idp_session_clients), which is
 *    what makes a later logout reach this tool;
 * 3. writes the sid into the code payload, exactly like the bridge does with
 *    the nonce, so IdTokenResponse can put it in the id_token.
 *
 * Bound to jeremy379/laravel-openid-connect 3.x internals (the code payload is
 * a CryptTrait-encrypted JSON); SessionControlTest (AC-SID-1) fails loudly if
 * an upgrade breaks it.
 */
class AuthCodeGrant extends BridgeAuthCodeGrant
{
    public function __construct(
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        DateInterval $authCodeTTL,
        private ResponseInterface $emptyResponse,
        CurrentRequestServiceInterface $currentRequestService,
    ) {
        parent::__construct($authCodeRepository, $refreshTokenRepository, $authCodeTTL, $emptyResponse, $currentRequestService);
    }

    public function completeAuthorizationRequest(AuthorizationRequestInterface $authorizationRequest): ResponseTypeInterface
    {
        $response = parent::completeAuthorizationRequest($authorizationRequest);

        if (! $authorizationRequest->isAuthorizationApproved() || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $sid = $this->sidFor($authorizationRequest);

        if ($sid === null) {
            return $response;
        }

        app(IdpSessions::class)->recordClient($sid, $authorizationRequest->getClient()->getIdentifier());

        $location = $response->generateHttpResponse($this->emptyResponse)->getHeader('Location')[0] ?? '';
        $parsed = parse_url($location);

        if (! is_array($parsed) || ! isset($parsed['query'])) {
            return $response;
        }

        parse_str($parsed['query'], $query);

        if (! isset($query['code']) || ! is_string($query['code'])) {
            return $response;
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($this->decrypt($query['code']), true, 512, JSON_THROW_ON_ERROR);
        $payload['sid'] = $sid;
        $query['code'] = $this->encrypt(json_encode($payload, JSON_THROW_ON_ERROR));
        $parsed['query'] = http_build_query($query);

        $response->setRedirectUri(self::unparseUrl($parsed));

        return $response;
    }

    /**
     * The sid of the browser session that is approving this request — only
     * when that session belongs to the very user the code is for.
     */
    private function sidFor(AuthorizationRequestInterface $authorizationRequest): ?string
    {
        $guard = Auth::guard('web');
        $user = $guard->user();
        $subject = $authorizationRequest->getUser()?->getIdentifier();

        if (! $user instanceof User || (string) $user->getKey() !== (string) $subject || ! method_exists($guard, 'getSession')) {
            return null;
        }

        return app(IdpSessions::class)->ensure($guard->getSession(), $user);
    }

    /**
     * Inverse of parse_url (the bridge's own helper is private).
     *
     * @param  array<string, int|string>  $parts
     */
    private static function unparseUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $user = (string) ($parts['user'] ?? '');
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? $user.$pass.'@' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.$auth.$host.$port.$path.$query.$fragment;
    }
}
