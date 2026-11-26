<?php

// Nexo ID's side of CONTRATO-F3 §0.1, v2.1 item 2, and its own sign-out page
// (item 9 of F3 tanda 2):
//
//   - end_session WITHOUT a hint, with client_id + post_logout_redirect_uri: a
//     tool whose session expired has no id_token left to send. The URI is
//     honoured only when it is registered for THAT client — after the
//     confirmation with a session, at once without one.
//   - POST /logout with an expired page (419) asks «¿Cerrar sesión en todo
//     Nexo?» instead of showing the 419.
//   - Every sign-out of Nexo ID lands on «Cerraste sesión en todo Nexo», with
//     the way back in.

use App\Models\IdpSession;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

function sofClient(string $host): OauthClient
{
    /** @var OauthClient $client */
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: $host,
        redirectUris: ["https://{$host}/auth/nexo/callback", "https://{$host}/auth/nexo/signed-out"],
        confidential: false,
        user: null,
    );

    return $client;
}

/** The current browser's sid, started as the Login event would. */
function sofSignIn(User $user): string
{
    test()->actingAs($user);
    $sid = Str::random(40);
    IdpSession::query()->create(['sid' => $sid, 'user_id' => $user->getKey(), 'last_seen_at' => now()]);
    session()->put('nexo_idp.sid', $sid);

    return $sid;
}

/** A genuine id_token for $client (authorize + token, PKCE), as whoever is signed in. */
function sofIdToken(OauthClient $client): string
{
    $verifier = str_repeat('v', 64);
    $redirect = $client->redirect_uris[0];
    $location = (string) test()->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 's',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]))->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

    return (string) test()->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirect,
        'code_verifier' => $verifier,
        'code' => $params['code'] ?? '',
    ])->assertOk()->json('id_token');
}

/** Make CSRF really run: the test client skips it while unit-testing. */
function sofEnforceCsrf(): void
{
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
}

beforeEach(function () {
    Http::fake(fn () => Http::response('', 200));
});

// ── end_session with client_id and no hint ──────────────────────────────────

it('end_session without a hint, signed in: asks first, then returns to the tool\'s registered page with its state', function () {
    $client = sofClient('tool-a.test');
    $sid = sofSignIn(User::factory()->create());

    $page = $this->get(route('openid.end_session_endpoint', [
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
        'state' => 'st-1',
    ]));

    $page->assertOk()->assertViewIs('auth.logout-confirm')
        ->assertSee('name="client_id" value="'.$client->getKey().'"', false)
        ->assertSee('name="post_logout_redirect_uri" value="https://tool-a.test/auth/nexo/signed-out"', false)
        ->assertSee('name="state" value="st-1"', false);
    $this->assertAuthenticated('web');
    expect(IdpSession::query()->find($sid)->ended_at)->toBeNull();

    $this->post(route('openid.end_session.confirm'), [
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
        'state' => 'st-1',
    ])->assertRedirect('https://tool-a.test/auth/nexo/signed-out?state=st-1');

    $this->assertGuest('web');
    expect(IdpSession::query()->find($sid)->ended_reason)->toBe('end_session');
});

it('end_session without a hint and without a session goes straight back to the registered page', function () {
    $client = sofClient('tool-a.test');

    $this->get(route('openid.end_session_endpoint', [
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
        'state' => 'st-2',
    ]))->assertRedirect('https://tool-a.test/auth/nexo/signed-out?state=st-2');
});

it('end_session never honours a URI that is not registered for THAT client', function () {
    $a = sofClient('tool-a.test');
    sofClient('tool-b.test');

    // Registered, but for another client.
    $this->get(route('openid.end_session_endpoint', [
        'client_id' => $a->getKey(),
        'post_logout_redirect_uri' => 'https://tool-b.test/auth/nexo/signed-out',
    ]))->assertOk()->assertViewIs('auth.logged-out');

    // Not registered anywhere.
    $this->get(route('openid.end_session_endpoint', [
        'client_id' => $a->getKey(),
        'post_logout_redirect_uri' => 'https://evil.test/steal',
    ]))->assertOk()->assertViewIs('auth.logged-out');

    // A client that does not exist.
    $this->get(route('openid.end_session_endpoint', [
        'client_id' => (string) Str::uuid(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
    ]))->assertOk()->assertViewIs('auth.logged-out');
});

it('end_session ignores a revoked client\'s URIs', function () {
    $client = sofClient('tool-a.test');
    $client->forceFill(['revoked' => true])->save();

    $this->get(route('openid.end_session_endpoint', [
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
    ]))->assertOk()->assertViewIs('auth.logged-out');
});

it('the confirmation does not trust its form: a forged return URI lands on our own page', function () {
    $client = sofClient('tool-a.test');
    sofSignIn(User::factory()->create());

    // The GET with an unregistered URI renders no return fields at all…
    $this->get(route('openid.end_session_endpoint', [
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://evil.test/steal',
    ]))->assertOk()->assertViewIs('auth.logout-confirm')
        ->assertDontSee('name="post_logout_redirect_uri"', false);

    // …and a POST that adds them anyway is checked again.
    $this->post(route('openid.end_session.confirm'), [
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://evil.test/steal',
    ])->assertRedirect(route('signed-out'));
    $this->assertGuest('web');
});

it('with a valid hint, a client_id that is not the hint\'s audience is not honoured', function () {
    $a = sofClient('tool-a.test');
    $b = sofClient('tool-b.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $hint = sofIdToken($a);

    // Tool A's genuine hint cannot carry tool B's client_id to bounce to B.
    $this->get(route('openid.end_session_endpoint', [
        'id_token_hint' => $hint,
        'client_id' => $b->getKey(),
        'post_logout_redirect_uri' => 'https://tool-b.test/auth/nexo/signed-out',
    ]))->assertOk()->assertViewIs('auth.logged-out');
    $this->assertGuest('web');
});

it('with a valid hint and its own client_id, the registered page is honoured', function () {
    $a = sofClient('tool-a.test');
    $this->actingAs(User::factory()->create());
    $hint = sofIdToken($a);

    $this->get(route('openid.end_session_endpoint', [
        'id_token_hint' => $hint,
        'client_id' => $a->getKey(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
    ]))->assertRedirect('https://tool-a.test/auth/nexo/signed-out');
});

// ── POST /logout with an expired page ───────────────────────────────────────

it('POST /logout with an expired page asks «¿Cerrar sesión en todo Nexo?» instead of a 419, and signs nobody out by itself', function () {
    sofEnforceCsrf();
    $user = User::factory()->create();
    $sid = sofSignIn($user);

    // A redirect to the GET confirmation, never a page rendered from the
    // exception: that one skipped the web group's locale, CSP and no-store.
    $this->post(route('logout'), ['_token' => 'stale-token'])
        ->assertRedirect(route('openid.end_session_endpoint'));

    $this->assertAuthenticatedAs($user, 'web');
    expect(IdpSession::query()->find($sid)->ended_at)->toBeNull();

    $this->get(route('openid.end_session_endpoint'))
        ->assertOk()
        ->assertViewIs('auth.logout-confirm')
        ->assertSee(route('openid.end_session.confirm'))
        ->assertSee('name="_token"', false);
});

it('the expired-page confirmation goes through the whole web group: the person\'s language, the CSP and no-store', function () {
    sofEnforceCsrf();
    $user = User::factory()->create();
    sofSignIn($user);

    $this->post(route('logout'), ['_token' => 'stale-token'])->assertRedirect();

    $page = $this->withUnencryptedCookie('nexo-lang', 'en')->get(route('openid.end_session_endpoint'));

    $page->assertOk()->assertSee('Sign out of all of Nexo?')->assertSee($user->email);
    expect($page->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $page->headers->get('Content-Security-Policy'))->toContain('form-action');
});

it('«Cerrar sesión en todos los dispositivos» with an expired page goes back to the account, signing nobody out', function () {
    // Not the «this device» confirmation: confirming it would leave the other
    // devices signed in while the person asked for all of them.
    sofEnforceCsrf();
    $user = User::factory()->create();
    $sid = sofSignIn($user);

    $this->post(route('logout.everywhere'), ['_token' => 'stale-token'])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('status', __('The page expired. Try again.'));

    $this->assertAuthenticatedAs($user, 'web');
    expect(IdpSession::query()->find($sid)->ended_at)->toBeNull();
});

it('the confirmation itself with an expired page asks again, keeping the tool\'s return (validated again by the GET)', function () {
    sofEnforceCsrf();
    $client = sofClient('tool-a.test');
    $sid = sofSignIn(User::factory()->create());

    $this->post(route('openid.end_session.confirm'), [
        '_token' => 'stale-token',
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
        'state' => 'st-9',
        'evil' => 'x',
    ])->assertRedirect(route('openid.end_session_endpoint', [
        'client_id' => $client->getKey(),
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
        'state' => 'st-9',
    ]));

    $this->assertAuthenticated('web');
    expect(IdpSession::query()->find($sid)->ended_at)->toBeNull();
});

it('a malformed end_session parameter is ignored, not a server error', function () {
    sofSignIn(User::factory()->create());

    $this->get('/oauth/logout?client_id[]=x&post_logout_redirect_uri[]=https://evil.test&state[]=s&id_token_hint[]=h')
        ->assertOk()->assertViewIs('auth.logout-confirm')
        ->assertDontSee('name="post_logout_redirect_uri"', false);

    $this->post(route('openid.end_session.confirm'), ['client_id' => ['x'], 'post_logout_redirect_uri' => ['https://evil.test']])
        ->assertRedirect(route('signed-out'));
});

it('POST /logout with an expired page and no session left lands on the signed-out page', function () {
    sofEnforceCsrf();

    $this->post(route('logout'), ['_token' => 'stale-token'])
        ->assertRedirect(route('signed-out'));
});

it('a same-origin «Cerrar sesión» whose session already expired lands on the signed-out page, not on the login form', function () {
    // Browsers send Sec-Fetch-Site, and Laravel accepts a same-origin POST on
    // that alone: the stale token never 419s, but the session is gone.
    sofEnforceCsrf();

    $this->withHeader('Sec-Fetch-Site', 'same-origin')
        ->post(route('logout'), ['_token' => 'stale-token'])
        ->assertRedirect(route('signed-out'));
});

it('a cross-site POST /logout (a forged sign-out) only gets the confirmation', function () {
    sofEnforceCsrf();
    $user = User::factory()->create();
    $sid = sofSignIn($user);

    $this->withHeader('Sec-Fetch-Site', 'cross-site')
        ->post(route('logout'))
        ->assertRedirect(route('openid.end_session_endpoint'));

    $this->assertAuthenticatedAs($user, 'web');
    expect(IdpSession::query()->find($sid)->ended_at)->toBeNull();
});

it('any other form with an expired page still answers 419', function () {
    sofEnforceCsrf();
    sofSignIn(User::factory()->create());

    $this->patch('/profile', ['_token' => 'stale-token', 'display_name' => 'X'])->assertStatus(419);
});

// ── «Cerraste sesión en todo Nexo» ──────────────────────────────────────────

it('POST /logout lands on «Cerraste sesión en todo Nexo», with the way back in', function () {
    sofSignIn(User::factory()->create());

    $this->post(route('logout'))->assertRedirect(route('signed-out'));

    $this->get(route('signed-out'))
        ->assertOk()
        ->assertSee(__('You signed out of all of Nexo'))
        ->assertSee(__('Your Nexo ID session ended on this device, and so did the Nexo tools you opened with it.'))
        ->assertSee('data-nexo-sign-in-again', false)
        ->assertSee('href="'.route('login').'"', false);
});

it('«en todos los dispositivos» lands on its own wording', function () {
    sofSignIn(User::factory()->create());

    $this->post(route('logout.everywhere'))->assertRedirect(route('signed-out', ['reason' => 'everywhere']));

    $this->get(route('signed-out', ['reason' => 'everywhere']))
        ->assertOk()
        ->assertSee(__('You signed out on every device'));
});

it('the signed-out page is never shown to someone still signed in', function () {
    sofSignIn(User::factory()->create());

    $this->get(route('signed-out'))->assertRedirect(route('profile.show'));
});

it('the signed-out page says it in Spanish with the glossary words', function () {
    app()->setLocale('es');

    expect(__('You signed out of all of Nexo'))->toBe('Cerraste sesión en todo Nexo');
    expect(__('Sign in again'))->toBe('Volver a entrar');
    expect(__('Sign out this session'))->toBe('Cerrar esta sesión');
    expect(__('Sign out all other sessions'))->toBe('Cerrar las demás sesiones');
    expect(__('Sign out of all devices'))->toBe('Cerrar sesión en todos los dispositivos');
    expect(__('Sign out of all of Nexo?'))->toBe('¿Cerrar sesión en todo Nexo?');
});
