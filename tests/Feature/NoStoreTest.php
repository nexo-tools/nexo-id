<?php

// AC-NOSTORE-1 (ADR-010, contract §3): nothing personal stays in a browser or
// proxy cache. Without `no-store` the back button after signing out showed the
// account page again (F1 evaluation).

use App\Models\User;
use Laravel\Passport\ClientRepository;

function noStoreClient()
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'No-store Client',
        redirectUris: ['https://client.test/callback'],
        confidential: false,
        user: null,
    );
}

function expectNoStore($response): void
{
    $header = (string) $response->headers->get('Cache-Control');

    expect($header)->toContain('no-store')->toContain('private');
}

it('AC-NOSTORE-1: the account entry points are never cached', function (string $uri) {
    expectNoStore($this->get($uri));
})->with(['/login', '/register', '/forgot-password', '/reset-password/some-token']);

it('AC-NOSTORE-1: every response to a signed-in session is never cached', function () {
    $user = User::factory()->create();

    expectNoStore($this->actingAs($user)->get('/profile')->assertOk());
    // Even a public page, once it is rendered for a signed-in person.
    expectNoStore($this->actingAs($user)->get('/')->assertOk());
    expectNoStore($this->actingAs($user)->get('/help')->assertOk());
});

it('AC-NOSTORE-1: the OAuth/OIDC surface is never cached', function () {
    $client = noStoreClient();
    $user = User::factory()->create();

    expectNoStore($this->get('/oauth/authorize?client_id='.$client->getKey()));
    expectNoStore($this->get('/oauth/logout'));
    expectNoStore($this->actingAs($user)->get('/oauth/logout'));
    expectNoStore($this->post('/oauth/token', ['grant_type' => 'authorization_code']));
    expectNoStore($this->getJson('/oauth/userinfo'));
});

it('AC-NOSTORE-1: public pages and the public OIDC documents stay cacheable', function () {
    expect((string) $this->get('/')->headers->get('Cache-Control'))->not->toContain('no-store');
    expect((string) $this->get('/.well-known/openid-configuration')->headers->get('Cache-Control'))->not->toContain('no-store');
    expect((string) $this->get('/oauth/jwks')->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('AC-NOSTORE-1: the sign-out pages are never cached, for a guest too', function () {
    // They are what the back button lands on after signing out: a cached copy
    // of the redirect or of the page could bring an older state back.
    expectNoStore($this->get('/signed-out')->assertOk());
    expectNoStore($this->get('/signed-out?reason=everywhere')->assertOk());
    expectNoStore($this->post('/logout')->assertRedirect());
});
