<?php

namespace App\Providers;

use App\Oidc\AuthCodeGrant;
use App\Oidc\IdTokenResponse;
use DateInterval;
use Illuminate\Encryption\Encrypter;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Nyholm\Psr7\Response;
use OpenIDConnect\ClaimExtractor;
use OpenIDConnect\Claims\ClaimSet;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;
use OpenIDConnect\Laravel\PassportServiceProvider as BridgeServiceProvider;

/**
 * The OIDC bridge's service provider with our two extensions swapped in
 * (ADR-010): the auth-code grant that registers the IdP session and carries
 * `sid` in the code, and the id_token response that copies it into the
 * id_token. Everything else is the bridge's (and, through it, Passport's).
 *
 * Registered instead of the bridge's provider: composer.json lists
 * jeremy379/laravel-openid-connect under dont-discover (as it already did with
 * laravel/passport) and bootstrap/providers.php names this class. Both
 * providers registered at once would register Passport's routes twice and
 * break `route:cache`.
 */
class OidcServiceProvider extends BridgeServiceProvider
{
    public function makeAuthorizationServer(?ResponseTypeInterface $responseType = null): AuthorizationServer
    {
        $cryptKey = $this->makeCryptKey('private');
        $encryptionKey = $this->getEncryptionKey(app(Encrypter::class)->getKey());

        /** @var array<string, array<int, string>> $customClaimSets */
        $customClaimSets = (array) config('openid.custom_claim_sets', []);

        $claimSets = array_map(
            fn (array $claims, string $name): ClaimSet => new ClaimSet($name, $claims),
            $customClaimSets,
            array_keys($customClaimSets),
        );

        $responseType = new IdTokenResponse(
            app(config('openid.repositories.identity')),
            new ClaimExtractor(...$claimSets),
            Configuration::forSymmetricSigner(
                app(config('openid.signer')),
                InMemory::plainText($cryptKey->getKeyContents(), $cryptKey->getPassPhrase() ?? ''),
            ),
            (array) config('openid.token_headers', []),
            (bool) config('openid.use_microseconds', true),
            app(LaravelCurrentRequestService::class),
            $encryptionKey,
            (string) config('openid.issuedBy', 'laravel'),
        );

        return new AuthorizationServer(
            app(ClientRepository::class),
            app(AccessTokenRepository::class),
            app(ScopeRepository::class),
            $cryptKey,
            $encryptionKey,
            $responseType,
        );
    }

    protected function buildAuthCodeGrant(): AuthCodeGrant
    {
        return new AuthCodeGrant(
            $this->app->make(AuthCodeRepository::class),
            $this->app->make(RefreshTokenRepository::class),
            new DateInterval('PT10M'),
            new Response,
            $this->app->make(LaravelCurrentRequestService::class),
        );
    }
}
