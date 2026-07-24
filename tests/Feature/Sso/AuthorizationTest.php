<?php

use App\Models\User;
use Laravel\Passport\ClientRepository;

/** Create a first-party public (PKCE) authorization-code client. */
function ssoClient(array $redirects = ['https://client.test/callback'])
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test Client',
        redirectUris: $redirects,
        confidential: false,
        user: null,
    );
}

/** PKCE code_verifier + S256 code_challenge. */
function pkce(): array
{
    $verifier = str_repeat('a', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function authorizeUrl(string $clientId, string $redirect, string $challenge, string $scope = 'openid profile email'): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => 'xyz-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);
}

it('AC-AUTH-1: silently issues a code to a first-party client for a verified user (no consent)', function () {
    $client = ssoClient();
    [, $challenge] = pkce();
    $user = User::factory()->create(); // verified

    $response = $this->actingAs($user)
        ->get(authorizeUrl($client->getKey(), 'https://client.test/callback', $challenge));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://client.test/callback?');
    expect($location)->toContain('code=');
    expect($location)->toContain('state=xyz-state');
});

it('AC-AUTH-2: redirects an unauthenticated user to login', function () {
    $client = ssoClient();
    [, $challenge] = pkce();

    $this->get(authorizeUrl($client->getKey(), 'https://client.test/callback', $challenge))
        ->assertRedirect(route('login'));
});

it('AC-AUTH-3: blocks an unverified user from obtaining a code', function () {
    $client = ssoClient();
    [, $challenge] = pkce();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(authorizeUrl($client->getKey(), 'https://client.test/callback', $challenge))
        ->assertRedirect(route('verification.notice'));
});

it('AC-AUTH-4: rejects an unknown client id', function () {
    [, $challenge] = pkce();
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(authorizeUrl('00000000-0000-0000-0000-000000000000', 'https://client.test/callback', $challenge));

    // Passport surfaces an invalid-client error, never a code redirect.
    expect((string) $response->headers->get('Location'))->not->toContain('code=');
    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('AC-AUTH-5: requires PKCE for a public client', function () {
    $client = ssoClient();
    $user = User::factory()->create();

    $url = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'xyz-state',
        // no code_challenge
    ]);

    $response = $this->actingAs($user)->get($url);

    // Without PKCE the public client cannot get a code.
    expect((string) $response->headers->get('Location'))->not->toContain('code=');
});

it('AC-PKCE-1: rejects plain code_challenge_method (no PKCE downgrade)', function () {
    $client = ssoClient();
    [, $challenge] = pkce();
    $user = User::factory()->create();

    $url = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'xyz-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'plain',
    ]);

    $this->actingAs($user)->get($url)->assertStatus(400);
});

it('AC-PKCE-1: rejects an omitted code_challenge_method (league defaults to plain)', function () {
    $client = ssoClient();
    [, $challenge] = pkce();
    $user = User::factory()->create();

    $url = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'xyz-state',
        'code_challenge' => $challenge,
        // code_challenge_method intentionally omitted
    ]);

    $this->actingAs($user)->get($url)->assertStatus(400);
});

it('AC-PKCE-1: still issues a code for S256 (enforcement does not break the happy path)', function () {
    $client = ssoClient();
    [, $challenge] = pkce();
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(authorizeUrl($client->getKey(), 'https://client.test/callback', $challenge));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))->toContain('code=');
});

it('AC-PKCE-2: discovery advertises S256 only (no plain)', function () {
    $methods = $this->getJson('/.well-known/openid-configuration')
        ->assertOk()
        ->json('code_challenge_methods_supported');

    expect($methods)->toBe(['S256']);
});

it('AC-CLIENT-2: rejects a redirect_uri that is not registered exactly', function () {
    $client = ssoClient(['https://client.test/callback']);
    [, $challenge] = pkce();
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(authorizeUrl($client->getKey(), 'https://client.test/evil', $challenge));

    expect((string) $response->headers->get('Location'))->not->toContain('code=');
    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});
