<?php

// Session control (ADR-010, contract F3 §2.1–§2.3): Nexo ID owns the session
// of the ecosystem. Every login has a sid that travels in the id_token, every
// code registers its client against that sid, and ending a sid sends a signed
// logout_token to exactly the tools that had a code in it.

use App\Actions\ChangeUserPassword;
use App\Auth\Backchannel\BackchannelDelivery;
use App\Auth\Backchannel\LogoutTokenIssuer;
use App\Auth\IdpSessions;
use App\Jobs\DeliverBackchannelLogout;
use App\Models\IdpSession;
use App\Models\OauthClient;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/** A first-party client; with a back-channel URI unless $backchannel is null. */
function scClient(string $host, ?string $backchannel = 'default', array $extraRedirects = []): OauthClient
{
    /** @var OauthClient $client */
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: $host,
        redirectUris: ["https://{$host}/callback", ...$extraRedirects],
        confidential: false,
        user: null,
    );

    if ($backchannel !== null) {
        $client->forceFill(['backchannel_logout_uri' => $backchannel === 'default' ? "https://{$host}/backchannel" : $backchannel])->save();
    }

    return $client;
}

function scVerifier(): string
{
    return str_repeat('s', 64);
}

/** Authorize (as whoever the test client is signed in as) and return the code. */
function scCode(OauthClient $client, array $extra = []): string
{
    $challenge = rtrim(strtr(base64_encode(hash('sha256', scVerifier(), true)), '+/', '-_'), '=');

    $location = (string) test()->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => $client->redirect_uris[0],
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => 'st',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        ...$extra,
    ]))->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

    expect($params)->toHaveKey('code');

    return (string) $params['code'];
}

/** Full authorize + token round trip; returns the id_token. */
function scIdToken(OauthClient $client, array $extra = []): string
{
    $code = scCode($client, $extra);

    return (string) test()->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => $client->redirect_uris[0],
        'code_verifier' => scVerifier(),
        'code' => $code,
    ])->assertOk()->json('id_token');
}

/**
 * Verify a JWT the way a client does: against the public JWKS. The JWKS is
 * single-key and kid-less (as the id_token), so the sole key is used directly.
 *
 * @return array<string, mixed>
 */
function scVerify(string $jwt): array
{
    $jwks = test()->getJson('/oauth/jwks')->assertOk()->json();

    return json_decode((string) json_encode(JWT::decode($jwt, JWK::parseKey($jwks['keys'][0], 'RS256'))), true);
}

/** @return array<string, mixed> */
function scHeader(string $jwt): array
{
    return json_decode(JWT::urlsafeB64Decode(explode('.', $jwt)[0]), true);
}

/** The logout_tokens Http::fake captured, verified, keyed by nothing (in send order). */
function scSentLogoutTokens(): array
{
    return Http::recorded()
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (HttpRequest $r): bool => isset($r->data()['logout_token']))
        ->map(fn (HttpRequest $r): array => ['url' => $r->url(), 'claims' => scVerify($r->data()['logout_token'])])
        ->values()
        ->all();
}

/** Register a second device's sid for $user, with $clients recorded on it. */
function scOtherDeviceSid(User $user, OauthClient ...$clients): string
{
    $sid = Str::random(40);
    IdpSession::query()->create(['sid' => $sid, 'user_id' => $user->getKey(), 'last_seen_at' => now()]);

    foreach ($clients as $client) {
        app(IdpSessions::class)->recordClient($sid, $client->getKey());
    }

    return $sid;
}

/** A database session row whose payload carries $sid, as DatabaseSessionHandler writes it. */
function scSessionRow(User $user, string $id, ?string $sid): void
{
    $payload = json_encode(['_token' => Str::random(40), 'nexo_idp' => $sid === null ? [] : ['sid' => $sid]]);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->getKey(),
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Other Browser',
        'payload' => base64_encode((string) $payload),
        'last_activity' => now()->timestamp,
    ]);
}

function scCurrentSid(): ?string
{
    return session(IdpSessions::SESSION_KEY);
}

/**
 * What the tools answer, by URL pattern (Str::is); anything else answers 200.
 * One Http::fake per test reads this map — calling Http::fake() twice stacks
 * stubs and the first registration wins (see AGENTS.md).
 *
 * @param  array<string, mixed>|null  $set
 * @return array<string, mixed>
 */
function scToolResponses(?array $set = null): array
{
    static $map = [];

    if ($set !== null) {
        $map = $set;
    }

    return $map;
}

beforeEach(function () {
    scToolResponses([]);

    Http::fake(function (HttpRequest $request) {
        foreach (scToolResponses() as $pattern => $response) {
            if (Str::is($pattern, $request->url())) {
                return $response;
            }
        }

        return Http::response('', 200);
    });
});

// ── sid (§2.1) ───────────────────────────────────────────────────────────────

it('AC-SID-1: the id_token carries sid and nonce, and the client is registered against that sid', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create();

    $this->actingAs($user);
    $claims = scVerify(scIdToken($client, ['nonce' => 'nonce-123']));

    expect($claims['nonce'])->toBe('nonce-123');
    expect($claims['sid'])->toBeString()->toHaveLength(40);
    expect($claims['sub'])->toBe($user->getKey());

    $session = IdpSession::query()->find($claims['sid']);
    expect($session)->not->toBeNull();
    expect($session->user_id)->toBe($user->getKey());
    expect(DB::table('idp_session_clients')->where('sid', $claims['sid'])->pluck('client_id')->all())
        ->toBe([$client->getKey()]);
});

it('AC-SID-2: a password login starts the sid, and it survives session()->regenerate()', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect();

    $sid = scCurrentSid();
    expect($sid)->toBeString()->toHaveLength(40);
    expect(IdpSession::query()->where('user_id', $user->getKey())->count())->toBe(1);

    // The login itself already regenerated the id; regenerate again mid-login.
    app('session.store')->regenerate();

    expect(scVerify(scIdToken($client))['sid'])->toBe($sid);
    expect(scVerify(scIdToken($client))['sid'])->toBe($sid);
    expect(IdpSession::query()->where('user_id', $user->getKey())->count())->toBe(1);
});

it('AC-SID-3: a session opened before session control gets a sid lazily on its first authorize', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create();

    // actingAs fires no Login event: exactly the pre-deploy session.
    $this->actingAs($user);
    expect(scCurrentSid())->toBeNull();

    $sid = scVerify(scIdToken($client))['sid'];

    expect(scCurrentSid())->toBe($sid);
    expect(IdpSession::query()->whereKey($sid)->where('user_id', $user->getKey())->exists())->toBeTrue();
});

it('AC-SID-4: signing in through the «remember me» cookie also starts a sid', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => $token = Str::random(60)])->save();

    $guard = auth()->guard('web');
    $cookie = $user->getKey().'|'.$token.'|'.$guard->hashPasswordForCookie($user->getAuthPassword());

    $this->withCookie($guard->getRecallerName(), $cookie)->get('/profile')->assertOk();

    expect(scCurrentSid())->toBeString();
    expect(IdpSession::query()->whereKey(scCurrentSid())->where('user_id', $user->getKey())->exists())->toBeTrue();
});

it('AC-SID-5: a browser whose sid was ended elsewhere is signed out on its next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $sid = app(IdpSessions::class)->ensure(app('session.store'), $user);

    app(IdpSessions::class)->end([$sid], 'session_revoked');

    $this->get('/profile')->assertRedirect(route('login'));
    $this->assertGuest('web');
});

// ── discovery (§2.3) ─────────────────────────────────────────────────────────

it('AC-BCL-P1: discovery advertises back-channel logout with session support, and the kill-switch hides it', function () {
    $doc = $this->getJson('/.well-known/openid-configuration')->assertOk();
    expect($doc->json('backchannel_logout_supported'))->toBeTrue();
    expect($doc->json('backchannel_logout_session_supported'))->toBeTrue();

    config()->set('nexo.backchannel.enabled', false);

    $doc = $this->getJson('/.well-known/openid-configuration')->assertOk();
    expect($doc->json())->not->toHaveKey('backchannel_logout_supported');
    expect($doc->json())->not->toHaveKey('backchannel_logout_session_supported');
});

// ── the logout_token (§2.3) ──────────────────────────────────────────────────

it('AC-BCL-P2: the logout_token is a JWT per Back-Channel Logout 1.0, signed with the JWKS key', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $idToken = scIdToken($client);
    $idClaims = scVerify($idToken);

    $this->post('/logout')->assertRedirect(route('home'));

    $recorded = Http::recorded()->map(fn (array $pair) => $pair[0])->values();
    expect($recorded)->toHaveCount(1);

    /** @var HttpRequest $request */
    $request = $recorded[0];
    expect($request->url())->toBe('https://tool-a.test/backchannel');
    expect($request->method())->toBe('POST');
    expect($request->header('Content-Type')[0])->toContain('application/x-www-form-urlencoded');
    expect(array_keys($request->data()))->toBe(['logout_token']);

    $jwt = $request->data()['logout_token'];
    $header = scHeader($jwt);
    expect($header['typ'])->toBe('logout+jwt');
    expect($header['alg'])->toBe('RS256');
    // Same header set as the id_token (no kid today, single-key JWKS).
    expect(array_key_exists('kid', $header))->toBe(array_key_exists('kid', scHeader($idToken)));

    $claims = scVerify($jwt); // signature against the public JWKS, exp enforced
    expect($claims['iss'])->toBe($idClaims['iss']);
    expect($claims['aud'])->toBe($client->getKey());
    expect($claims['sub'])->toBe($user->getKey());
    expect($claims['sid'])->toBe($idClaims['sid']);
    expect($claims['iat'])->toBeInt();
    expect($claims['exp'])->toBe($claims['iat'] + 120);
    expect($claims['jti'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    expect($claims)->not->toHaveKey('nonce');
    expect($claims['events'])->toHaveKey(LogoutTokenIssuer::EVENT);

    // The event member is an empty JSON object, not an array.
    $payload = JWT::urlsafeB64Decode(explode('.', $jwt)[1]);
    expect($payload)->toContain('"events":{"http://schemas.openid.net/event/backchannel-logout":{}}');
});

it('AC-BCL-P2: every logout_token gets its own jti', function () {
    $issuer = app(LogoutTokenIssuer::class);
    $delivery = new BackchannelDelivery('client', 'https://tool.test/bc', 'sub-1', 'sid-1');

    $a = scVerify($issuer->issue($delivery, 'http://localhost'));
    $b = scVerify($issuer->issue($delivery, 'http://localhost'));

    expect($a['jti'])->not->toBe($b['jti']);
});

// ── who gets told (§2.2) ─────────────────────────────────────────────────────

it('AC-BCL-P3: POST /logout tells only the tools of the current sid that registered a URI', function () {
    $withUri = scClient('tool-a.test');
    $withoutUri = scClient('tool-b.test', backchannel: null);
    $user = User::factory()->create();

    $this->actingAs($user);
    $current = scVerify(scIdToken($withUri))['sid'];
    scIdToken($withoutUri);
    $other = scOtherDeviceSid($user, $withUri);

    $this->post('/logout');

    $sent = scSentLogoutTokens();
    expect($sent)->toHaveCount(1);
    expect($sent[0]['url'])->toBe('https://tool-a.test/backchannel');
    expect($sent[0]['claims']['sid'])->toBe($current);

    expect(IdpSession::query()->find($current)->ended_reason)->toBe('logout');
    expect(IdpSession::query()->find($other)->ended_at)->toBeNull();
});

it('AC-BCL-P3: end_session with a valid id_token_hint ends the hint sid and redirects to a registered URI', function () {
    $client = scClient('tool-a.test', extraRedirects: ['https://tool-a.test/auth/nexo/signed-out']);
    $user = User::factory()->create();
    $this->actingAs($user);
    $idToken = scIdToken($client);
    $sid = scVerify($idToken)['sid'];

    $this->get(route('openid.end_session_endpoint', [
        'id_token_hint' => $idToken,
        'post_logout_redirect_uri' => 'https://tool-a.test/auth/nexo/signed-out',
        'state' => 'abc',
    ]))->assertRedirect('https://tool-a.test/auth/nexo/signed-out?state=abc');

    $this->assertGuest('web');
    expect(IdpSession::query()->find($sid)->ended_reason)->toBe('end_session');
    expect(collect(scSentLogoutTokens())->pluck('claims.sid')->all())->toBe([$sid]);
});

it('AC-BCL-P3: end_session with a valid hint and no browser session still ends the hint sid', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $idToken = scIdToken($client);
    $sid = scVerify($idToken)['sid'];

    // The browser no longer has a Nexo ID session (e.g. it expired).
    auth()->guard('web')->logout();
    app('session.store')->flush();

    $this->get(route('openid.end_session_endpoint', ['id_token_hint' => $idToken]))
        ->assertOk()->assertViewIs('auth.logged-out');

    expect(IdpSession::query()->find($sid)->ended_at)->not->toBeNull();
    expect(collect(scSentLogoutTokens())->pluck('claims.sid')->all())->toBe([$sid]);
});

it('AC-BCL-P3: «Cerrar esta sesión» ends only that session\'s sid', function () {
    config()->set('session.driver', 'database');
    $client = scClient('tool-a.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $current = scVerify(scIdToken($client))['sid'];
    $target = scOtherDeviceSid($user, $client);
    $bystander = scOtherDeviceSid($user, $client);
    scSessionRow($user, 'target-row', $target);
    scSessionRow($user, 'bystander-row', $bystander);

    $this->delete(route('sessions.destroy', 'target-row'))->assertRedirect();

    expect(collect(scSentLogoutTokens())->pluck('claims.sid')->all())->toBe([$target]);
    expect(IdpSession::query()->find($target)->ended_reason)->toBe('session_revoked');
    expect(IdpSession::query()->find($current)->ended_at)->toBeNull();
    expect(IdpSession::query()->find($bystander)->ended_at)->toBeNull();
    expect(DB::table('sessions')->where('id', 'target-row')->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', 'bystander-row')->exists())->toBeTrue();
});

it('AC-BCL-P3: «Cerrar las demás sesiones» ends every sid but the current one', function () {
    config()->set('session.driver', 'database');
    $client = scClient('tool-a.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $current = scVerify(scIdToken($client))['sid'];
    $a = scOtherDeviceSid($user, $client);
    $b = scOtherDeviceSid($user, $client);
    $someoneElse = scOtherDeviceSid(User::factory()->create(), $client);

    $this->delete(route('sessions.destroy-others'))->assertRedirect();

    expect(collect(scSentLogoutTokens())->pluck('claims.sid')->sort()->values()->all())
        ->toBe(collect([$a, $b])->sort()->values()->all());
    expect(IdpSession::query()->find($current)->ended_at)->toBeNull();
    expect(IdpSession::query()->find($someoneElse)->ended_at)->toBeNull();
    $this->assertAuthenticated('web');
});

it('AC-BCL-P3: «Cerrar sesión en todos los dispositivos» ends every sid, this one included', function () {
    config()->set('session.driver', 'database');
    $client = scClient('tool-a.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $current = scVerify(scIdToken($client))['sid'];
    $other = scOtherDeviceSid($user, $client);
    scSessionRow($user, 'other-row', $other);

    $this->post(route('logout.everywhere'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect(collect(scSentLogoutTokens())->pluck('claims.sid')->sort()->values()->all())
        ->toBe(collect([$current, $other])->sort()->values()->all());
    expect(IdpSession::query()->whereKey([$current, $other])->where('ended_reason', 'all_devices')->count())->toBe(2);
    expect(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeFalse();
    $this->assertGuest('web');
});

it('AC-BCL-P3: changing the password ends every other sid; resetting it ends all of them', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create(['password' => 'old-password-1']);
    $this->actingAs($user);
    $current = scVerify(scIdToken($client))['sid'];
    $other = scOtherDeviceSid($user, $client);

    $this->put(route('password.update'), [
        'current_password' => 'old-password-1',
        'password' => 'new-password-2',
        'password_confirmation' => 'new-password-2',
    ])->assertSessionHasNoErrors();

    expect(collect(scSentLogoutTokens())->pluck('claims.sid')->all())->toBe([$other]);
    expect(IdpSession::query()->find($other)->ended_reason)->toBe('password_changed');
    expect(IdpSession::query()->find($current)->ended_at)->toBeNull();

    // A reset (no session kept) ends the remaining one too.
    app(ChangeUserPassword::class)($user->fresh(), 'reset-password-3');
    expect(IdpSession::query()->find($current)->ended_reason)->toBe('password_reset');
});

it('AC-BCL-P3: account deletion sends a sub-only logout_token to every client the account used', function () {
    $used = scClient('tool-a.test');
    $alsoUsed = scClient('tool-b.test');
    $neverUsed = scClient('tool-c.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $sid = scVerify(scIdToken($used))['sid'];
    scIdToken($alsoUsed);

    app(IdpSessions::class)->endForAccountDeletion($user);

    $sent = collect(scSentLogoutTokens());
    $subOnly = $sent->filter(fn (array $t): bool => ! array_key_exists('sid', $t['claims']));
    expect($subOnly->pluck('url')->sort()->values()->all())
        ->toBe(['https://tool-a.test/backchannel', 'https://tool-b.test/backchannel']);
    expect($subOnly->pluck('claims.sub')->unique()->all())->toBe([$user->getKey()]);
    expect($sent->pluck('url')->all())->not->toContain('https://tool-c.test/backchannel');
    expect(IdpSession::query()->find($sid)->ended_reason)->toBe('account_deleted');
    expect($neverUsed->fresh())->not->toBeNull();
});

it('AC-BCL-P3: ending a sid twice notifies once', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $sid = scVerify(scIdToken($client))['sid'];

    app(IdpSessions::class)->end([$sid], 'logout');
    app(IdpSessions::class)->end([$sid], 'logout');

    expect(scSentLogoutTokens())->toHaveCount(1);
});

// ── a tool that is down (§2.3) ───────────────────────────────────────────────

it('AC-BCL-P4: a client that is down does not block the logout and is queued with backoff for 24 h', function () {
    scToolResponses([
        'https://tool-down.test/*' => Http::failedConnection(),
        'https://tool-error.test/*' => Http::response('', 503),
        'https://tool-refuses.test/*' => Http::response('{"error":"invalid_request"}', 400),
    ]);
    Queue::fake();

    $up = scClient('tool-up.test');
    $down = scClient('tool-down.test');
    $error = scClient('tool-error.test');
    $refuses = scClient('tool-refuses.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    foreach ([$up, $down, $error, $refuses] as $client) {
        scIdToken($client);
    }

    $this->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest('web');

    // Transient failures are queued, first retry one minute later…
    Queue::assertPushed(DeliverBackchannelLogout::class, 2);
    foreach (['tool-down.test', 'tool-error.test'] as $host) {
        Queue::assertPushed(DeliverBackchannelLogout::class, function (DeliverBackchannelLogout $job) use ($host): bool {
            return $job->delivery->uri === "https://{$host}/backchannel"
                && $job->delay !== null
                && abs(now()->addMinute()->getTimestamp() - $job->delay->getTimestamp()) <= 5
                && abs($job->retryUntil()->getTimestamp() - now()->addDay()->getTimestamp()) <= 5;
        });
    }
    // …with the contract's backoff (5, 15, 60 min, then hourly).
    expect((new DeliverBackchannelLogout(new BackchannelDelivery('c', 'u', 's', null), 'i', 0))->backoff())->toBe([300, 900, 3600]);

    // A definitive refusal (400) is not retried; the healthy tool got its token.
    Queue::assertNotPushed(DeliverBackchannelLogout::class, fn (DeliverBackchannelLogout $job): bool => str_contains($job->delivery->uri, 'tool-refuses'));
    expect(collect(scSentLogoutTokens())->pluck('url')->all())->toContain('https://tool-up.test/backchannel');
});

it('AC-BCL-P4: the inline attempt gives up after ~2 s, in parallel, however many tools are down', function () {
    Http::swap(new Factory); // real network: no fake
    Queue::fake();

    $user = User::factory()->create();
    $sid = Str::random(40);
    IdpSession::query()->create(['sid' => $sid, 'user_id' => $user->getKey()]);
    // A non-routable address: the connection never completes.
    foreach (['10.255.255.1:81', '10.255.255.1:82'] as $i => $host) {
        $client = scClient("blackhole-{$i}.test", backchannel: "http://{$host}/backchannel");
        app(IdpSessions::class)->recordClient($sid, $client->getKey());
    }

    $started = microtime(true);
    app(IdpSessions::class)->end([$sid], 'logout');
    $elapsed = microtime(true) - $started;

    expect($elapsed)->toBeLessThan(3.5);
    Queue::assertPushed(DeliverBackchannelLogout::class, 2);
});

it('AC-BCL-P4: more deliveries than sync_max go straight to the queue', function () {
    Queue::fake();
    config()->set('nexo.backchannel.sync_max', 1);

    $user = User::factory()->create();
    $this->actingAs($user);
    scIdToken(scClient('tool-a.test'));
    scIdToken(scClient('tool-b.test'));

    $this->post('/logout');

    expect(scSentLogoutTokens())->toBe([]);
    Queue::assertPushed(DeliverBackchannelLogout::class, 2);
});

it('AC-BCL-P4: the queued retry mints a fresh valid token, and throws to retry while the client is down', function () {
    $user = User::factory()->create();
    $delivery = new BackchannelDelivery('client-x', 'https://tool-a.test/backchannel', $user->getKey(), 'sid-x');
    $job = new DeliverBackchannelLogout($delivery, 'https://issuer.test', now()->addDay()->getTimestamp());

    $job->handle(app(LogoutTokenIssuer::class));

    $claims = scSentLogoutTokens()[0]['claims'];
    expect($claims['iss'])->toBe('https://issuer.test');
    expect($claims['sid'])->toBe('sid-x');
    expect($claims['exp'] - time())->toBeGreaterThan(100);

    scToolResponses(['*' => Http::response('', 502)]);
    expect(fn () => $job->handle(app(LogoutTokenIssuer::class)))->toThrow(RuntimeException::class);

    scToolResponses(['*' => Http::failedConnection()]);
    expect(fn () => $job->handle(app(LogoutTokenIssuer::class)))->toThrow(RuntimeException::class);
});

it('AC-BCL-P5: NEXO_OIDC_BACKCHANNEL=false sends and queues nothing, but sids still end', function () {
    Queue::fake();
    config()->set('nexo.backchannel.enabled', false);

    $user = User::factory()->create();
    $this->actingAs($user);
    $sid = scVerify(scIdToken(scClient('tool-a.test')))['sid'];

    $this->post('/logout');

    expect(scSentLogoutTokens())->toBe([]);
    Queue::assertNothingPushed();
    expect(IdpSession::query()->find($sid)->ended_reason)->toBe('logout');
});

// ── end_session without a valid hint (§2.2, S9) ──────────────────────────────

it('AC-BCL-P6: end_session without a hint asks first, and only the POST signs out of everything', function () {
    $client = scClient('tool-a.test');
    $user = User::factory()->create();
    $this->actingAs($user);
    $sid = scVerify(scIdToken($client))['sid'];

    $page = $this->get(route('openid.end_session_endpoint'));
    $page->assertOk()->assertViewIs('auth.logout-confirm')
        ->assertSee(route('openid.end_session.confirm'))
        ->assertSee('name="_token"', false);

    // Nothing happened yet: still signed in, nobody told.
    $this->assertAuthenticated('web');
    expect(IdpSession::query()->find($sid)->ended_at)->toBeNull();
    expect(scSentLogoutTokens())->toBe([]);

    $this->post(route('openid.end_session.confirm'))->assertOk()->assertViewIs('auth.logged-out');

    $this->assertGuest('web');
    expect(IdpSession::query()->find($sid)->ended_reason)->toBe('end_session');
    expect(collect(scSentLogoutTokens())->pluck('claims.sid')->all())->toBe([$sid]);
});

it('AC-BCL-P6: a forged hint, or a genuine hint of another account, also asks first', function () {
    $client = scClient('tool-a.test');
    $attacker = User::factory()->create();
    $victim = User::factory()->create();

    // The attacker mints a genuine id_token for their own account…
    $this->actingAs($attacker);
    $attackerToken = scIdToken($client);
    $attackerSid = scVerify($attackerToken)['sid'];

    // …and the victim opens the link while signed in.
    auth()->guard('web')->logout();
    app('session.store')->flush();
    $this->actingAs($victim);

    $this->get(route('openid.end_session_endpoint', ['id_token_hint' => $attackerToken]))
        ->assertOk()->assertViewIs('auth.logout-confirm');

    [$h, $p] = explode('.', $attackerToken);
    $this->get(route('openid.end_session_endpoint', ['id_token_hint' => "{$h}.{$p}.forged-signature"]))
        ->assertOk()->assertViewIs('auth.logout-confirm');

    $this->assertAuthenticatedAs($victim, 'web');
    expect(IdpSession::query()->find($attackerSid)->ended_at)->toBeNull();
    expect(scSentLogoutTokens())->toBe([]);
});

it('AC-BCL-P6: the confirmation is a CSRF-protected POST in the web group', function () {
    // The test client skips CSRF checks while unit-testing, so assert the
    // wiring instead: the confirming route is POST-only, rides the web group
    // (ValidateCsrfToken) and is not exempted anywhere.
    $route = app('router')->getRoutes()->getByName('openid.end_session.confirm');

    expect($route->methods())->toBe(['POST']);
    expect($route->gatherMiddleware())->toContain('web');
    expect((new ReflectionClass(ValidateCsrfToken::class))->getStaticPropertyValue('neverVerify'))
        ->not->toContain('oauth/logout');
});

it('AC-SID-6: the registry prunes ended and long-idle sessions, and keeps the live ones', function () {
    $user = User::factory()->create();
    $client = scClient('tool-a.test');
    $make = function (array $attributes) use ($user, $client): string {
        $sid = Str::random(40);
        IdpSession::query()->create(['sid' => $sid, 'user_id' => $user->getKey(), ...$attributes]);
        app(IdpSessions::class)->recordClient($sid, $client->getKey());
        IdpSession::query()->whereKey($sid)->update($attributes);

        return $sid;
    };

    $endedLongAgo = $make(['ended_at' => now()->subDays(31), 'ended_reason' => 'logout']);
    $endedRecently = $make(['ended_at' => now()->subDays(2), 'ended_reason' => 'logout']);
    $idle = $make(['last_seen_at' => now()->subDays(46)]);
    $live = $make(['last_seen_at' => now()->subDays(3)]);

    $this->artisan('nexo:prune-idp-sessions')->expectsOutput('Pruned 1 ended and 1 idle IdP session(s).')->assertSuccessful();

    expect(IdpSession::query()->pluck('sid')->sort()->values()->all())
        ->toBe(collect([$endedRecently, $live])->sort()->values()->all());
    expect(DB::table('idp_session_clients')->whereIn('sid', [$endedLongAgo, $idle])->exists())->toBeFalse();
});
