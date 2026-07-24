<?php

use App\Models\User;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

function oidcClient(array $redirects = ['https://client.test/callback'])
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'OIDC Client',
        redirectUris: $redirects,
        confidential: false,
        user: null,
    );
}

function pkcePair(): array
{
    $verifier = str_repeat('b', 64);

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

/** Run the authorize step as $user and return the issued authorization code. */
function getAuthCode(string $clientId, string $redirect, string $challenge, User $user, string $scope = 'openid profile email'): string
{
    $test = test();
    $location = $test->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => 'st',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->headers->get('Location');

    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $params);

    return $params['code'] ?? '';
}

it('AC-TOKEN-1: exchanges a code + verifier for access + id tokens', function () {
    $client = oidcClient();
    [$verifier, $challenge] = pkcePair();
    $user = User::factory()->create();

    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user);

    $response = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ]);

    $response->assertOk();
    $body = $response->json();
    expect($body)->toHaveKeys(['access_token', 'id_token', 'token_type', 'expires_in']);
    expect($body['token_type'])->toBe('Bearer');
});

it('AC-TOKEN-2: rejects a wrong PKCE code_verifier', function () {
    $client = oidcClient();
    [, $challenge] = pkcePair();
    $user = User::factory()->create();

    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user);

    $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => str_repeat('z', 64), // wrong verifier
        'code' => $code,
    ])->assertStatus(400);
});

it('AC-TOKEN-3: rejects reuse of an authorization code', function () {
    $client = oidcClient();
    [$verifier, $challenge] = pkcePair();
    $user = User::factory()->create();

    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user);

    $payload = [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ];

    $this->post('/oauth/token', $payload)->assertOk();
    $this->post('/oauth/token', $payload)->assertStatus(400); // second use rejected
});

it('AC-OIDC-1: userinfo returns sub and scoped claims', function () {
    $client = oidcClient();
    [$verifier, $challenge] = pkcePair();
    $user = User::factory()->create(['display_name' => 'Ada Lovelace']);

    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user);
    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json('access_token');

    $info = $this->withToken($token)->getJson('/oauth/userinfo');

    $info->assertOk();
    expect($info->json('sub'))->toBe($user->id);
    expect($info->json('email'))->toBe($user->email);
    expect($info->json('email_verified'))->toBeTrue();
    expect($info->json('name'))->toBe('Ada Lovelace');
});

it('AC-OIDC-5: /oauth/userinfo rejects a missing or invalid token with 401', function () {
    // No token at all.
    $this->getJson('/oauth/userinfo')->assertStatus(401);

    // A syntactically-present but invalid bearer token.
    $this->withToken('not-a-real-access-token')->getJson('/oauth/userinfo')->assertStatus(401);
});

it('AC-OIDC-2: the id_token is a JWT with iss, aud, sub, exp', function () {
    $client = oidcClient();
    [$verifier, $challenge] = pkcePair();
    $user = User::factory()->create();

    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user);
    $idToken = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json('id_token');

    $parts = explode('.', (string) $idToken);
    expect($parts)->toHaveCount(3); // header.payload.signature

    $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    expect($claims)->toHaveKeys(['iss', 'aud', 'sub', 'exp']);
    expect($claims['sub'])->toBe($user->id);

    // The signature verifies against the provider's public key (what clients do
    // via the JWKS).
    $config = Configuration::forSymmetricSigner(
        new Sha256,
        InMemory::file(storage_path('oauth-public.key')),
    );
    $parsed = $config->parser()->parse((string) $idToken);
    $valid = $config->validator()->validate(
        $parsed,
        new SignedWith($config->signer(), $config->signingKey()),
    );
    expect($valid)->toBeTrue();
});

it('AC-OIDC-3: discovery and jwks are served', function () {
    $discovery = $this->getJson('/.well-known/openid-configuration');
    $discovery->assertOk();
    expect($discovery->json())->toHaveKeys([
        'issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri',
    ]);

    $jwks = $this->getJson('/oauth/jwks');
    $jwks->assertOk();
    expect($jwks->json('keys.0.kty'))->toBe('RSA');
});

it('AC-OIDC-4: sub equals the user uuid and is stable across logins', function () {
    $client = oidcClient();
    $user = User::factory()->create();

    $subs = collect(range(1, 2))->map(function () use ($client, $user) {
        [$verifier, $challenge] = pkcePair();
        $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user);

        return $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://client.test/callback',
            'code_verifier' => $verifier,
            'code' => $code,
        ])->json('access_token');
    })->map(function ($token) {
        return $this->withToken($token)->getJson('/oauth/userinfo')->json('sub');
    });

    expect($subs[0])->toBe($user->id)->toBe($subs[1]);
});

it('AC-RATE-2: throttles /oauth/userinfo per IP with 429', function () {
    $client = oidcClient();
    [$verifier, $challenge] = pkcePair();
    $user = User::factory()->create();

    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user);
    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json('access_token');

    // 60 authenticated requests/minute/IP are allowed; the next is 429.
    for ($i = 0; $i < 60; $i++) {
        expect($this->withToken($token)->getJson('/oauth/userinfo')->getStatusCode())->not->toBe(429);
    }

    $this->withToken($token)->getJson('/oauth/userinfo')->assertStatus(429);
});

it('AC-SCOPE-1: omits email/name claims (userinfo and id_token) when their scopes are not granted', function () {
    $client = oidcClient();
    [$verifier, $challenge] = pkcePair();
    $user = User::factory()->create();

    // Only openid scope — no profile, no email.
    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user, 'openid');
    $tokens = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json();

    // userinfo omits the ungranted claims.
    $info = $this->withToken($tokens['access_token'])->getJson('/oauth/userinfo');
    $info->assertOk();
    expect($info->json('sub'))->toBe($user->id);
    expect($info->json('email'))->toBeNull();
    expect($info->json('name'))->toBeNull();

    // The id_token carries sub but likewise omits email/name.
    $claims = json_decode(base64_decode(strtr(explode('.', (string) $tokens['id_token'])[1], '-_', '+/')), true);
    expect($claims['sub'])->toBe($user->id);
    expect($claims)->not->toHaveKey('email');
    expect($claims)->not->toHaveKey('name');
});

it('AC-SCOPE-2: no id_token is issued when the openid scope is absent', function () {
    $client = oidcClient();
    [$verifier, $challenge] = pkcePair();
    $user = User::factory()->create();

    // Plain OAuth2 code flow (no openid) — OIDC id_token must not be minted.
    $code = getAuthCode($client->getKey(), 'https://client.test/callback', $challenge, $user, 'email');
    $body = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json();

    expect($body)->toHaveKey('access_token');
    expect($body)->not->toHaveKey('id_token');
});
