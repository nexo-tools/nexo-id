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
