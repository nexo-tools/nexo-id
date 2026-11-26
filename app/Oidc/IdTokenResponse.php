<?php

namespace App\Oidc;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use OpenIDConnect\ClaimExtractor;
use OpenIDConnect\IdTokenResponse as BridgeIdTokenResponse;
use OpenIDConnect\Interfaces\CurrentRequestServiceInterface;
use OpenIDConnect\Interfaces\IdentityEntityInterface;
use OpenIDConnect\Interfaces\IdentityRepositoryInterface;
use Throwable;

/**
 * The bridge's id_token response plus the `sid` claim (ADR-010): read from the
 * authorization code payload, where App\Oidc\AuthCodeGrant put it — the same
 * path the bridge uses for `nonce`. A token minted without a code (refresh
 * grant) carries no sid; the tools never refresh, they re-authorize.
 */
class IdTokenResponse extends BridgeIdTokenResponse
{
    private ?CurrentRequestServiceInterface $requestService;

    /**
     * @param  array<string, mixed>  $tokenHeaders
     */
    public function __construct(
        IdentityRepositoryInterface $identityRepository,
        ClaimExtractor $claimExtractor,
        Configuration $config,
        array $tokenHeaders = [],
        bool $useMicroseconds = true,
        ?CurrentRequestServiceInterface $currentRequestService = null,
        mixed $encryptionKey = null,
        string $issuedByConfigured = 'laravel',
    ) {
        parent::__construct($identityRepository, $claimExtractor, $config, $tokenHeaders, $useMicroseconds, $currentRequestService, $encryptionKey, $issuedByConfigured);
        $this->requestService = $currentRequestService;
    }

    protected function getBuilder(AccessTokenEntityInterface $accessToken, IdentityEntityInterface $userEntity): Builder
    {
        $builder = parent::getBuilder($accessToken, $userEntity);

        $sid = $this->sidFromCode();

        return $sid !== null ? $builder->withClaim('sid', $sid) : $builder;
    }

    private function sidFromCode(): ?string
    {
        $body = $this->requestService?->getRequest()->getParsedBody();

        if (! is_array($body) || ! isset($body['code']) || ! is_string($body['code'])) {
            return null;
        }

        try {
            $payload = json_decode($this->decrypt($body['code']), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null; // the grant already rejected a bad code before we get here
        }

        $sid = is_array($payload) ? ($payload['sid'] ?? null) : null;

        return is_string($sid) && $sid !== '' ? $sid : null;
    }
}
