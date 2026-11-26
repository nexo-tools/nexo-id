<?php

namespace App\Auth\Backchannel;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;
use OpenIDConnect\Services\IssuedByGetter;
use stdClass;

/**
 * Mints OIDC Back-Channel Logout 1.0 logout_tokens (ADR-010, contract §2.3).
 *
 * Signed RS256 with the same Passport key — and the same header set, `kid`
 * included if one is ever configured — as the id_token, so a client verifies
 * both against the one JWKS. Today the id_token carries no `kid` (single-key
 * JWKS, see INTEGRATION.md), and neither does this.
 *
 * Claims: iss (as in the id_token), aud (client_id), iat, exp = iat + 120,
 * jti (uuid v4), sub, sid (omitted only for the sub-only account-deletion
 * token) and the back-channel `events` member. Never a nonce: the spec forbids
 * it so a logout_token can never be confused with an id_token.
 */
class LogoutTokenIssuer
{
    public const EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    public const TTL_SECONDS = 120;

    public function issue(BackchannelDelivery $delivery, string $issuer): string
    {
        $now = new DateTimeImmutable('@'.time());

        $builder = (new Builder(new JoseEncoder, ChainedFormatter::default()))
            ->issuedBy($issuer)
            ->permittedFor($delivery->clientId)
            ->issuedAt($now)
            ->expiresAt($now->modify('+'.self::TTL_SECONDS.' seconds'))
            ->identifiedBy((string) Str::uuid())
            ->relatedTo($delivery->sub)
            ->withClaim('events', [self::EVENT => new stdClass]);

        if ($delivery->sid !== null) {
            $builder = $builder->withClaim('sid', $delivery->sid);
        }

        /** @var array<string, mixed> $headers */
        $headers = (array) config('openid.token_headers', []);
        foreach ($headers as $name => $value) {
            $builder = $builder->withHeader($name, $value);
        }

        $builder = $builder->withHeader('typ', 'logout+jwt');

        return $builder->getToken(new Sha256, $this->signingKey())->toString();
    }

    /**
     * The issuer exactly as the id_token states it. Inside a request that is
     * the bridge's own getter (scheme + host of the request); a queued retry
     * has no request, so the dispatcher resolves it at dispatch time and the
     * job carries it. Outside any request (a console command), the app URL.
     */
    public static function currentIssuer(): string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $url = parse_url((string) config('app.url'));
            $port = isset($url['port']) ? ':'.$url['port'] : '';

            return ($url['scheme'] ?? 'https').'://'.($url['host'] ?? 'localhost').$port;
        }

        return IssuedByGetter::get(app(LaravelCurrentRequestService::class), (string) config('openid.issuedBy', 'laravel'));
    }

    private function signingKey(): InMemory
    {
        $key = str_replace('\\n', "\n", (string) config('passport.private_key', ''));

        if ($key === '') {
            $key = (string) file_get_contents(Passport::keyPath('oauth-private.key'));
        }

        return InMemory::plainText($key);
    }
}
