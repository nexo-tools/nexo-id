<?php

use App\Models\User;
use Laravel\Passport\ClientRepository;

function logoutTestClient()
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Logout Client',
        redirectUris: ['https://client.test/callback'],
        confidential: false,
        user: null,
    );
}

function challengeFor(): string
{
    return rtrim(strtr(base64_encode(hash('sha256', str_repeat('c', 64), true)), '+/', '-_'), '=');
}

function authorizeRequest(string $clientId, string $challenge): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 's',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);
}

/** Run the full authorize + token flow to obtain a genuine id_token (aud = client). */
function mintLogoutIdToken($client, string $redirect, string $challenge, string $verifier, User $user): string
{
    $test = test();
    $location = $test->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 's',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->headers->get('Location');

    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $params);

    return (string) $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirect,
        'code_verifier' => $verifier,
        'code' => $params['code'] ?? '',
    ])->json('id_token');
}

it('AC-LOGOUT-1: after central logout a silent authorize requires re-login', function () {
    $client = logoutTestClient();
    $challenge = challengeFor();
    $user = User::factory()->create();

    // While signed in, a silent authorize issues a code.
    $before = $this->actingAs($user)->get(authorizeRequest($client->getKey(), $challenge));
    expect((string) $before->headers->get('Location'))->toContain('code=');

    // Central logout ends the Nexo ID session.
    $this->post('/logout')->assertRedirect(route('home'));

    // A fresh (unauthenticated) authorize now goes to login instead of a code.
    $after = $this->get(authorizeRequest($client->getKey(), $challenge));
    $after->assertRedirect(route('login'));
    expect((string) $after->headers->get('Location'))->not->toContain('code=');
});

it('AC-LOGOUT-2: the OIDC end_session_endpoint ends the central session', function () {
    $client = logoutTestClient();
    $challenge = challengeFor();
    $user = User::factory()->create();

    // Signed in, a silent authorize issues a code.
    $before = $this->actingAs($user)->get(authorizeRequest($client->getKey(), $challenge));
    expect((string) $before->headers->get('Location'))->toContain('code=');

    // Front-channel logout via the end_session endpoint.
    $this->get(route('openid.end_session_endpoint'));

    // A fresh authorize now requires re-login: the central session is gone.
    $after = $this->get(authorizeRequest($client->getKey(), $challenge));
    $after->assertRedirect(route('login'));
    expect((string) $after->headers->get('Location'))->not->toContain('code=');
});

it('AC-LOGOUT-3: redirects to a post_logout_redirect_uri registered for the id_token_hint client (with state)', function () {
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Logout Client',
        redirectUris: ['https://client.test/callback', 'https://client.test/after-logout'],
        confidential: false,
        user: null,
    );
    $verifier = str_repeat('c', 64);
    $challenge = challengeFor();
    $user = User::factory()->create();

    $idToken = mintLogoutIdToken($client, 'https://client.test/callback', $challenge, $verifier, $user);

    $response = $this->get(route('openid.end_session_endpoint', [
        'id_token_hint' => $idToken,
        'post_logout_redirect_uri' => 'https://client.test/after-logout',
        'state' => 'xyz',
    ]));

    $response->assertRedirect('https://client.test/after-logout?state=xyz');

    // The central session ended as well.
    $this->get(authorizeRequest($client->getKey(), $challenge))->assertRedirect(route('login'));
});

it('AC-LOGOUT-4: refuses an unregistered post_logout_redirect_uri (anti open-redirect)', function () {
    $client = logoutTestClient(); // only https://client.test/callback is registered
    $verifier = str_repeat('c', 64);
    $challenge = challengeFor();
    $user = User::factory()->create();

    $idToken = mintLogoutIdToken($client, 'https://client.test/callback', $challenge, $verifier, $user);

    $response = $this->get(route('openid.end_session_endpoint', [
        'id_token_hint' => $idToken,
        'post_logout_redirect_uri' => 'https://evil.test/steal',
    ]));

    // Our own signed-out page — never a bounce to the attacker's URL.
    $response->assertOk()->assertViewIs('auth.logged-out');
    expect($response->headers->get('Location'))->toBeNull();
});

it('AC-LOGOUT-5: without an id_token_hint, no post_logout_redirect_uri is honoured', function () {
    // The endpoint cannot attribute a URL to a client without the hint, so it
    // refuses to redirect even to a URL that is registered for some client.
    logoutTestClient();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('openid.end_session_endpoint', [
        'post_logout_redirect_uri' => 'https://client.test/callback',
    ]));

    $response->assertOk()->assertViewIs('auth.logged-out');
    expect($response->headers->get('Location'))->toBeNull();
});

it('AC-LOGOUT-6: discovery advertises the end_session_endpoint', function () {
    $discovery = $this->getJson('/.well-known/openid-configuration')->assertOk();

    $discovery->assertJsonStructure(['end_session_endpoint']);
    expect($discovery->json('end_session_endpoint'))->toContain('/oauth/logout');
});
