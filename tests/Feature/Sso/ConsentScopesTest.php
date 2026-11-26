<?php

// The consent screen of a third-party app (one with an owner: the Nexo tools
// skip it) lists what the app gets. The scope descriptions come from
// config/openid.php and are translated at render time; they must read as
// plain language, never protocol jargon («Habilitar OpenID Connect»).

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;

function csConsentPage(string $locale): TestResponse
{
    $owner = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Agenda de Ana',
        redirectUris: ['https://ana.example/callback'],
        confidential: false,
        user: $owner,
    );

    return test()->actingAs(User::factory()->create())->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://ana.example/callback',
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => 'cs',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('c', 64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'lang' => $locale,
    ]));
}

it('describes the openid scope in plain language, in every language', function (string $locale, string $plain) {
    $page = csConsentPage($locale);

    $page->assertOk()->assertViewIs('auth.oauth.authorize')->assertSee($plain);
    expect((string) $page->getContent())->not->toMatch('/OpenID Connect/i');
})->with([
    'es' => ['es', 'Confirmar que eres tú'],
    'en' => ['en', 'Confirm that it is you'],
    'pt' => ['pt', 'Confirmar que é você'],
]);

it('keeps the scope ids untouched (discovery still lists openid)', function () {
    $this->getJson('/.well-known/openid-configuration')->assertOk()
        ->assertJsonFragment(['scopes_supported' => ['openid', 'profile', 'email']]);
});
