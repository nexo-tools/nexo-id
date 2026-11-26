<?php

// «Alta que vuelve a la tool» (CONTRATO-F3 v2.2), Nexo ID's side: a tool's
// «Crear cuenta» asks /oauth/authorize for prompt=create. A visitor goes to the
// sign-up screen (which names the tool), verifies the email in the same
// browser and lands back in the tool with a code. With a session, create is
// ignored; none wins over create; an invalid client_id/redirect_uri names no
// tool and opens no redirect; the verified-email gate still holds.

use App\Models\OauthClient;
use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\ClientRepository;

const PC_CALLBACK = 'https://nexolinks.alvarocdev.com/auth/nexo/callback';

/** A first-party client living on a registered ecosystem tool's origin (Nexo Links). */
function pcClient(string $name = 'links-prod', array $redirects = [PC_CALLBACK]): OauthClient
{
    /** @var OauthClient $client */
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: $name,
        redirectUris: $redirects,
        confidential: false,
        user: null,
    );

    return $client;
}

function pcChallenge(): string
{
    return rtrim(strtr(base64_encode(hash('sha256', str_repeat('p', 64), true)), '+/', '-_'), '=');
}

/** @param  array<string, string>  $extra */
function pcAuthorize(OauthClient|string $client, array $extra = [], string $redirect = PC_CALLBACK): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $client instanceof OauthClient ? $client->getKey() : $client,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => 'pc-state',
        'code_challenge' => pcChallenge(),
        'code_challenge_method' => 'S256',
        'nonce' => 'pc-nonce',
        ...$extra,
    ]);
}

/** The prompt of a URL's query (null when there is none). */
function pcPrompt(string $url): ?string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return isset($query['prompt']) ? (string) $query['prompt'] : null;
}

function pcRegister(string $email = 'nueva@example.test'): User
{
    test()->post('/register', [
        'display_name' => 'Nueva Persona',
        'email' => $email,
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ])->assertRedirect(route('verification.notice'));

    return User::query()->where('email', $email)->firstOrFail();
}

/** The verification link the mail carries, read from the notification itself. */
function pcVerificationLink(User $user): string
{
    $link = null;

    Notification::assertSentTo($user, VerifyEmailQueued::class, function (VerifyEmailQueued $notification) use ($user, &$link): bool {
        $link = $notification->toMail($user)->viewData['url'] ?? null;

        return true;
    });

    expect($link)->toBeString();

    return (string) $link;
}

it('AC-CREATE-1: a visitor with prompt=create signs up, verifies the email and goes back to the tool with a code', function () {
    Notification::fake();
    $client = pcClient();

    $start = $this->get(pcAuthorize($client, ['prompt' => 'create']));

    // The sign-up screen, not the login, with the authorize request (without
    // `create`, so coming back does not ask again) as the intended URL.
    $start->assertRedirect(route('register'));
    $intended = (string) session('url.intended');
    expect($intended)->toStartWith(url('/oauth/authorize?'))
        ->and(pcPrompt($intended))->toBeNull()
        ->and($intended)->toContain('client_id='.$client->getKey())
        ->and($intended)->toContain('state=pc-state');

    // It names the tool: the registry's name for that origin, not the client's.
    $this->get(route('register'))->assertOk()
        ->assertSee('data-signup-tool', false)
        ->assertSee('To continue to Nexo Links');

    $user = pcRegister();
    $link = pcVerificationLink($user);

    // Verifying in the same browser resumes the authorize request …
    $verified = $this->get($link);
    $verified->assertRedirect($intended);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // … which, now with a verified session, answers the tool with a code.
    $back = (string) $this->get($intended)->headers->get('Location');
    expect($back)->toStartWith(PC_CALLBACK.'?')
        ->and($back)->toContain('code=')
        ->and($back)->toContain('state=pc-state');
});

it('AC-CREATE-1: keeps the other prompt values in the intended URL and drops only create', function () {
    $client = pcClient();

    $this->get(pcAuthorize($client, ['prompt' => 'consent create']))->assertRedirect(route('register'));

    expect(pcPrompt((string) session('url.intended')))->toBe('consent');
});

it('AC-CREATE-1: the sign-up screen in Spanish and Portuguese names the tool too', function (string $locale, string $text) {
    $client = pcClient();
    $this->get(pcAuthorize($client, ['prompt' => 'create']));

    $this->get(route('register', ['lang' => $locale]))->assertOk()->assertSee($text);
})->with([
    'es' => ['es', 'Para entrar a Nexo Links'],
    'pt' => ['pt', 'Para entrar no Nexo Links'],
]);

it('AC-CREATE-1: «Crear cuenta» from the login of an authorize request (no create) also names the tool and comes back', function () {
    Notification::fake();
    $client = pcClient();

    $this->get(pcAuthorize($client))->assertRedirect(route('login'));
    $intended = (string) session('url.intended');

    $this->get(route('register'))->assertOk()->assertSee('To continue to Nexo Links');

    $user = pcRegister();
    $this->get(pcVerificationLink($user))->assertRedirect($intended);

    expect((string) $this->get($intended)->headers->get('Location'))->toStartWith(PC_CALLBACK.'?code=');
});

it('AC-CREATE-2: with a session, prompt=create is ignored (straight to the code)', function () {
    $client = pcClient();
    $user = User::factory()->create();

    $location = (string) $this->actingAs($user)->get(pcAuthorize($client, ['prompt' => 'create']))->headers->get('Location');

    expect($location)->toStartWith(PC_CALLBACK.'?code=');
});

it('AC-CREATE-3: prompt=none wins over create (login_required to the tool, no sign-up screen)', function () {
    $client = pcClient();

    $location = (string) $this->get(pcAuthorize($client, ['prompt' => 'none create']))->headers->get('Location');

    expect($location)->toStartWith(PC_CALLBACK.'?')
        ->and($location)->toContain('error=login_required')
        ->and($location)->not->toContain('code=')
        ->and(session('url.intended'))->toBeNull();
});

it('AC-CREATE-4: an unknown client_id names no tool and opens no redirect', function () {
    $response = $this->get(pcAuthorize('00000000-0000-0000-0000-000000000000', ['prompt' => 'create']));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and($response->headers->get('Location'))->toBeNull()
        ->and(session('url.intended'))->toBeNull();

    $this->get(route('register'))->assertOk()->assertDontSee('data-signup-tool', false)->assertDontSee('To continue to');
});

it('AC-CREATE-4: an unregistered redirect_uri names no tool and opens no redirect', function () {
    $client = pcClient();

    $response = $this->get(pcAuthorize($client, ['prompt' => 'create'], 'https://evil.test/callback'));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and((string) $response->headers->get('Location'))->not->toContain('evil.test')
        ->and(session('url.intended'))->toBeNull();

    $this->get(route('register'))->assertOk()->assertDontSee('data-signup-tool', false);
});

it('AC-CREATE-4: an error Passport sends back to the tool (an unknown scope) stays that error, not the sign-up screen', function () {
    $client = pcClient();

    $location = (string) $this->get(pcAuthorize($client, ['prompt' => 'create', 'scope' => 'openid admin']))->headers->get('Location');

    // Only Passport's «sign in first» becomes /register; any other answer of
    // a valid request goes back untouched and leaves no intended URL behind.
    expect($location)->toStartWith(PC_CALLBACK.'?')
        ->and($location)->toContain('error=invalid_scope')
        ->and(session('url.intended'))->toBeNull();
});

it('AC-CREATE-4: a revoked client names no tool', function () {
    $client = pcClient();
    $client->forceFill(['revoked' => true])->save();

    $response = $this->get(pcAuthorize($client, ['prompt' => 'create']));

    expect($response->headers->get('Location'))->toBeNull()
        ->and(session('url.intended'))->toBeNull();
    $this->get(route('register'))->assertOk()->assertDontSee('data-signup-tool', false);
});

it('AC-CREATE-4: the sign-up screen only vouches for a genuine authorize URL in url.intended', function (Closure $intended) {
    $client = pcClient();

    $this->withSession(['url.intended' => $intended($client)])
        ->get(route('register'))->assertOk()->assertDontSee('data-signup-tool', false);
})->with([
    'another page' => [fn (OauthClient $c): string => url('/profile?client_id='.$c->getKey().'&redirect_uri='.urlencode(PC_CALLBACK))],
    'another host' => [fn (OauthClient $c): string => 'https://evil.test'.pcAuthorize($c)],
    'unregistered redirect_uri' => [fn (OauthClient $c): string => url(pcAuthorize($c, [], 'https://evil.test/callback'))],
    'revoked client' => [function (OauthClient $c): string {
        $c->forceFill(['revoked' => true])->save();

        return url(pcAuthorize($c));
    }],
    'array client_id' => [fn (OauthClient $c): string => url('/oauth/authorize?client_id[]='.$c->getKey().'&redirect_uri='.urlencode(PC_CALLBACK))],
]);

it('AC-CREATE-4: a non-ecosystem client is named by its OAuth client name', function () {
    $client = pcClient('Agenda de Ana', ['https://ana.example/callback']);

    $this->get(pcAuthorize($client, ['prompt' => 'create'], 'https://ana.example/callback'))->assertRedirect(route('register'));

    $this->get(route('register'))->assertOk()->assertSee('To continue to Agenda de Ana');
});

it('AC-CREATE-5: signing up never skips the email verification (no code until the link is opened)', function () {
    Notification::fake();
    $client = pcClient();

    $this->get(pcAuthorize($client, ['prompt' => 'create']));
    $intended = (string) session('url.intended');
    pcRegister();

    // Straight back to the authorize request without verifying: the
    // verification notice, never a code.
    $response = $this->get($intended);
    $response->assertRedirect(route('verification.notice'));
    expect((string) $response->headers->get('Location'))->not->toContain('code=');

    // Asking for create again while signed in (unverified) changes nothing.
    $again = $this->get(pcAuthorize($client, ['prompt' => 'create']));
    $again->assertRedirect(route('verification.notice'));
});

it('AC-CREATE-5: asking the same tool again before verifying keeps the sign-up request; another tool replaces it', function () {
    Notification::fake();
    $client = pcClient();
    $other = pcClient('events-prod', ['https://nexoevents.alvarocdev.com/auth/nexo/callback']);

    $this->get(pcAuthorize($client, ['prompt' => 'create']));
    $signUp = (string) session('url.intended');
    $user = pcRegister();

    // The same tool again (a plain sign-in, new state): the sign-up request
    // stays — the tool keeps that flow for an hour, a sign-in flow ten minutes.
    $this->get(pcAuthorize($client, ['state' => 'later']))->assertRedirect(route('verification.notice'));
    expect(session('url.intended'))->toBe($signUp);

    // Another tool: the latest destination wins, and verifying resumes it.
    $this->get(pcAuthorize($other, ['state' => 'events'], 'https://nexoevents.alvarocdev.com/auth/nexo/callback'))
        ->assertRedirect(route('verification.notice'));
    $intended = (string) session('url.intended');
    expect($intended)->toContain('client_id='.$other->getKey())->and($intended)->toContain('state=events');

    $this->get(pcVerificationLink($user))->assertRedirect($intended);
    expect((string) $this->get($intended)->headers->get('Location'))
        ->toStartWith('https://nexoevents.alvarocdev.com/auth/nexo/callback?code=');
});

it('AC-CREATE-5: the sign-up redirect is not cacheable', function () {
    $client = pcClient();

    $response = $this->get(pcAuthorize($client, ['prompt' => 'create']));

    $response->assertRedirect(route('register'));
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('AC-CREATE-6: discovery advertises prompt=create', function () {
    $this->getJson('/.well-known/openid-configuration')->assertOk()
        ->assertJsonPath('prompt_values_supported', ['none', 'login', 'consent', 'create']);
});

it('AC-CREATE-2: with a session, prompt=login create is just prompt=login (the login, never the sign-up screen)', function () {
    $client = pcClient();
    $user = User::factory()->create();

    $this->actingAs($user)->get(pcAuthorize($client, ['prompt' => 'login create']))->assertRedirect(route('login'));
});
