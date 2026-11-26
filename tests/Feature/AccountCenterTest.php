<?php

// Nexo ID as the account center of the ecosystem (experiencia unificada F3,
// evaluacion/nexoid/REPORTE.md): the «guardado» notices that never showed
// (H-nexoid-07), readable sessions (H-nexoid-06), «Herramientas conectadas»
// (H-nexoid-03) and «Borrar mi cuenta» (H-nexoid-10).

use App\Auth\IdpSessions;
use App\Models\IdpSession;
use App\Models\OauthClient;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/** A first-party client living on a registry tool's origin (its callback), with a back-channel URI. */
function acToolClient(string $key, ?string $backchannel = 'default'): OauthClient
{
    $url = rtrim((string) config("nexo-ecosystem.tools.{$key}.url"), '/');

    /** @var OauthClient $client */
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: "client-{$key}",
        redirectUris: ["{$url}/auth/nexo/callback", "{$url}/auth/nexo/signed-out"],
        confidential: false,
        user: null,
    );

    if ($backchannel !== null) {
        $client->forceFill(['backchannel_logout_uri' => $backchannel === 'default' ? "{$url}/auth/nexo/backchannel-logout" : $backchannel])->save();
    }

    return $client;
}

/** An IdP session (sid) of $user that signed in to $clients, last seen $minutesAgo. */
function acSid(User $user, int $minutesAgo, OauthClient ...$clients): string
{
    $sid = Str::random(40);
    IdpSession::query()->create(['sid' => $sid, 'user_id' => $user->getKey(), 'last_seen_at' => now()->subMinutes($minutesAgo)]);

    foreach ($clients as $client) {
        DB::table('idp_session_clients')->insert([
            'sid' => $sid,
            'client_id' => $client->getKey(),
            'created_at' => now()->subMinutes($minutesAgo),
            'last_seen_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    return $sid;
}

/** A browser session row of $user, as DatabaseSessionHandler writes it, carrying $sid. */
function acSessionRow(User $user, string $id, ?string $sid, string $userAgent, int $minutesAgo = 5): void
{
    $payload = json_encode(['_token' => Str::random(40), 'nexo_idp' => $sid === null ? [] : ['sid' => $sid]]);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->getKey(),
        'ip_address' => '203.0.113.9',
        'user_agent' => $userAgent,
        'payload' => base64_encode((string) $payload),
        'last_activity' => now()->subMinutes($minutesAgo)->timestamp,
    ]);
}

/** Every logout_token the fake tools received: [url, claims] (claims decoded, not verified — SessionControlTest verifies them). */
function acSentLogoutTokens(): array
{
    return Http::recorded()
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (HttpRequest $r): bool => isset($r->data()['logout_token']))
        ->map(fn (HttpRequest $r): array => [
            'url' => $r->url(),
            'claims' => json_decode(JWT::urlsafeB64Decode(explode('.', (string) $r->data()['logout_token'])[1]), true),
        ])
        ->values()
        ->all();
}

beforeEach(function () {
    Http::fake(fn () => Http::response('', 200));
});

// ── H-nexoid-07: the «guardado» notices ─────────────────────────────────────

it('H-nexoid-07: saving the profile says so on the page it lands on', function () {
    $user = User::factory()->create(['display_name' => 'Old Name']);

    $this->actingAs($user)->from('/profile')
        ->followingRedirects()
        ->patch('/profile', ['display_name' => 'New Name'])
        ->assertOk()
        ->assertSee('data-profile-status', false)
        ->assertSee(__('Your profile was updated.'));
});

it('H-nexoid-07: the notice also shows with the database session driver (the one production runs)', function () {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')
        ->followingRedirects()
        ->patch('/profile', ['display_name' => 'Another Name'])
        ->assertSee(__('Your profile was updated.'));
});

it('H-nexoid-07: changing the password and closing sessions say so too', function () {
    config()->set('session.driver', 'database');
    $user = User::factory()->create(['password' => 'old-password-1']);

    $this->actingAs($user)->from('/profile')->followingRedirects()
        ->put('/profile/password', [
            'current_password' => 'old-password-1',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])
        ->assertSee('data-profile-status', false);

    acSessionRow($user, 'other-device-row', null, 'Mozilla/5.0 (X11; Linux x86_64) Firefox/133.0');

    $this->actingAs($user->fresh())->from('/profile')->followingRedirects()
        ->delete('/profile/sessions/other-device-row')
        ->assertSee(__('That session was signed out.'));

    $this->actingAs($user->fresh())->from('/profile')->followingRedirects()
        ->delete('/profile/sessions')
        ->assertSee(__('All other sessions were signed out.'));
});

it('H-nexoid-12: the email reads as information, not as a field that looks editable', function () {
    $user = User::factory()->create(['email' => 'ana@example.test']);

    $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

    expect($html)->not->toContain('type="email"');
    expect($html)->toContain('ana@example.test');
});

// ── H-nexoid-06: readable sessions ──────────────────────────────────────────

it('H-nexoid-06: each session names its browser and system, its last activity and the tools it opened', function () {
    config()->set('session.driver', 'database');
    $events = acToolClient('nexoevents');
    $links = acToolClient('nexolinks');
    $user = User::factory()->create();
    $sid = acSid($user, 10, $events, $links);
    acSessionRow($user, 'phone-row', $sid, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 10);

    $html = $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36')
        ->get('/profile')->assertOk()->getContent();

    expect($html)->toContain(e(__(':browser on :system', ['browser' => 'Safari', 'system' => 'iPhone'])));
    expect($html)->toContain(e(__(':browser on :system', ['browser' => 'Chrome', 'system' => 'Windows'])));
    expect($html)->toContain(e(__('Used in :tools', ['tools' => 'Nexo Events, Nexo Links'])));
    expect($html)->toContain(e(__('Last active :time', ['time' => now()->subMinutes(10)->diffForHumans()])));
    expect($html)->not->toContain('Mozilla/5.0');
    expect(substr_count($html, 'data-session-current'))->toBe(1);
});

it('H-nexoid-06: the session buttons are named for what they close, never like the menu\'s «Cerrar sesión»', function () {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    acSessionRow($user, 'laptop-row', null, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15');

    $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

    preg_match_all('/<button[^>]*data-session-close[^>]*>\s*([^<]+?)\s*<\/button>/', $html, $rows);
    expect($rows[1])->toBe([__('Sign out this session')]);
    expect($html)->toContain('data-sessions-close-others')
        ->toContain(__('Sign out all other sessions'))
        ->toContain('data-sessions-close-all')
        ->toContain(__('Sign out of all devices'));

    // The only plain «Cerrar sesión» on the page is the account menu's and the
    // phone sheet's (AccountMenuTest pins that those are the only logout forms).
    $main = substr($html, (int) strpos($html, '<main'));
    expect(preg_match('/>\s*'.preg_quote(e(__('nexo.account.sign_out')), '/').'\s*</', $main))->toBe(0);
});

it('H-nexoid-06: the session confirmations use data-confirm, which the CSP lets run', function () {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    acSessionRow($user, 'second-row', null, 'Test Browser');

    $html = $this->actingAs($user)->get('/profile')->getContent();

    expect(preg_match('/<form[^>]*action="'.preg_quote(route('sessions.destroy-others'), '/').'"[^>]*data-confirm="/s', $html))->toBe(1);
    expect(preg_match('/<form[^>]*action="'.preg_quote(route('logout.everywhere'), '/').'"[^>]*data-confirm="/s', $html))->toBe(1);
});

// ── H-nexoid-03: connected tools ────────────────────────────────────────────

it('H-nexoid-03: lists the tools the account used, newest first, with when and «Abrir» to url + entry', function () {
    $events = acToolClient('nexoevents');
    $short = acToolClient('nexoshort');
    $unused = acToolClient('nexoagenda');
    $user = User::factory()->create();
    acSid($user, 60 * 24 * 3, $events);
    acSid($user, 7, $short);

    // A token from before session control counts too.
    $links = acToolClient('nexolinks');
    DB::table('oauth_access_tokens')->insert([
        'id' => Str::random(80), 'user_id' => $user->getKey(), 'client_id' => $links->getKey(),
        'scopes' => '[]', 'revoked' => false, 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20), 'expires_at' => now()->subDays(19),
    ]);

    $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

    expect(substr_count($html, 'data-connected-tool>'))->toBe(3);
    $short = strpos($html, '>Nexo Short<');
    $events = strpos($html, '>Nexo Events<');
    $links = strpos($html, '>Nexo Links<');
    expect($short)->toBeLessThan($events);
    expect($events)->toBeLessThan($links);
    expect($html)->not->toContain('>Nexo Agenda<');

    $entry = rtrim((string) config('nexo-ecosystem.tools.nexoshort.url'), '/').'/auth/nexo/enter';
    expect($html)->toContain('href="'.$entry.'" class="nexo-btn nexo-btn--ghost nexo-btn--sm min-h-11" data-connected-tool-open');
    expect($html)->toContain(e(__('Last used :time', ['time' => now()->subMinutes(7)->diffForHumans()])));
    expect($unused->fresh())->not->toBeNull();
});

it('H-nexoid-03: a revoked client is not listed, and a third-party one keeps its name with no «Abrir»', function () {
    $user = User::factory()->create();
    $revoked = acToolClient('nexoevents');
    $revoked->forceFill(['revoked' => true])->save();
    $third = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Agenda de Terceros', redirectUris: ['https://third.example.test/cb'], confidential: false, user: null,
    );
    acSid($user, 3, $revoked, $third);

    $html = $this->actingAs($user)->get('/profile')->getContent();

    expect($html)->not->toContain('>Nexo Events<');
    expect($html)->toContain('>Agenda de Terceros<');
    expect(substr_count($html, 'data-connected-tool-open'))->toBe(0);
});

it('H-nexoid-03: says so when the account has not used any tool', function () {
    $this->actingAs(User::factory()->create())->get('/profile')
        ->assertSee('data-connected-tools-empty', false);
});

it('H-nexoid-03: another account\'s tools never show', function () {
    $events = acToolClient('nexoevents');
    $someoneElse = User::factory()->create();
    acSid($someoneElse, 1, $events);

    $this->actingAs(User::factory()->create())->get('/profile')
        ->assertDontSee('>Nexo Events<', false)
        ->assertSee('data-connected-tools-empty', false);
});

// ── H-nexoid-10: delete the account ─────────────────────────────────────────

/**
 * An account with something in every table Nexo ID keeps about it, plus a
 * bystander with the same, so the cleanup is proven to stop at the account.
 *
 * @return array{0: User, 1: User, 2: array<int, OauthClient>}
 */
function acAccountWithEverything(): array
{
    config()->set('session.driver', 'database');

    $events = acToolClient('nexoevents');
    $links = acToolClient('nexolinks');
    $agendaNoBackchannel = acToolClient('nexoagenda', backchannel: null);

    $make = function (string $email) use ($events, $links, $agendaNoBackchannel): User {
        $user = User::factory()->create(['email' => $email, 'password' => 'right-password-1']);
        $sid = acSid($user, 5, $events, $agendaNoBackchannel);
        acSid($user, 50, $links);
        acSessionRow($user, 'row-'.Str::random(8), $sid, 'Test Browser');

        $access = Str::random(80);
        DB::table('oauth_access_tokens')->insert(['id' => $access, 'user_id' => $user->getKey(), 'client_id' => $links->getKey(), 'scopes' => '[]', 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour()]);
        DB::table('oauth_refresh_tokens')->insert(['id' => Str::random(80), 'access_token_id' => $access, 'revoked' => false, 'expires_at' => now()->addDays(30)]);
        DB::table('oauth_auth_codes')->insert(['id' => Str::random(80), 'user_id' => $user->getKey(), 'client_id' => $events->getKey(), 'scopes' => '[]', 'revoked' => false, 'expires_at' => now()->addMinutes(5)]);
        DB::table('oauth_device_codes')->insert(['id' => Str::random(80), 'user_id' => $user->getKey(), 'client_id' => $events->getKey(), 'user_code' => strtoupper(Str::random(8)), 'scopes' => '[]', 'revoked' => false, 'expires_at' => now()->addMinutes(5)]);
        DB::table('password_reset_tokens')->insert(['email' => $email, 'token' => Hash::make('t'), 'created_at' => now()]);

        return $user;
    };

    return [$make('leaving@example.test'), $make('staying@example.test'), [$events, $links, $agendaNoBackchannel]];
}

/** Rows that still belong to $user, per table. */
function acLeftovers(User $user, array $ownedClientIds = []): array
{
    $id = $user->getKey();
    $accessIds = DB::table('oauth_access_tokens')->where('user_id', $id)->pluck('id')->all();

    return array_filter([
        'users' => DB::table('users')->where('id', $id)->count(),
        'sessions' => DB::table('sessions')->where('user_id', $id)->count(),
        'idp_sessions' => DB::table('idp_sessions')->where('user_id', $id)->count(),
        'idp_session_clients' => DB::table('idp_session_clients')->whereIn('sid', DB::table('idp_sessions')->where('user_id', $id)->select('sid'))->count(),
        'oauth_access_tokens' => count($accessIds),
        'oauth_refresh_tokens' => DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $accessIds)->count(),
        'oauth_auth_codes' => DB::table('oauth_auth_codes')->where('user_id', $id)->count(),
        'oauth_device_codes' => DB::table('oauth_device_codes')->where('user_id', $id)->count(),
        'password_reset_tokens' => DB::table('password_reset_tokens')->where('email', $user->email)->count(),
        'oauth_clients (owned)' => DB::table('oauth_clients')->whereIn('id', $ownedClientIds)->count(),
    ]);
}

it('H-nexoid-10: /profile explains honestly what deleting does not delete, and lists the tools to visit first', function () {
    [$user] = acAccountWithEverything();

    $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();
    $section = substr($html, (int) strpos($html, 'data-delete-account'));

    expect($section)->toContain(e(__('What you have in each tool is not deleted with this, and afterwards you will not be able to get into it with this account. If you want to delete it, do it first in each tool.')));
    expect($section)->toContain('data-delete-account-tools');
    foreach (['Nexo Events', 'Nexo Links', 'Nexo Agenda'] as $tool) {
        expect($section)->toContain('>'.$tool.'<');
    }
    expect($section)->toContain('name="delete_password"');
    expect(preg_match('/<form[^>]*action="'.preg_quote(route('profile.destroy'), '/').'"[^>]*data-confirm="/s', $section))->toBe(1);
});

it('H-nexoid-10: deleting the account removes every row Nexo ID holds about it — in one go — and keeps everyone else\'s', function () {
    [$user, $bystander] = acAccountWithEverything();
    $owned = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'An app this account registered', redirectUris: ['https://dev.example.test/cb'], confidential: false, user: $user,
    );
    DB::table('oauth_access_tokens')->insert(['id' => Str::random(80), 'user_id' => $bystander->getKey(), 'client_id' => $owned->getKey(), 'scopes' => '[]', 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour()]);
    $bystanderBefore = acLeftovers($bystander);

    $this->actingAs($user)
        ->delete('/profile', ['delete_password' => 'right-password-1'])
        ->assertRedirect(route('home'));

    expect(acLeftovers($user, [$owned->getKey()]))->toBe([]);
    $this->assertGuest('web');

    // The bystander keeps all of it — except the token issued to the app the
    // deleted account owned, which went with that app.
    $bystanderAfter = acLeftovers($bystander);
    expect($bystanderAfter['oauth_access_tokens'])->toBe($bystanderBefore['oauth_access_tokens'] - 1);
    unset($bystanderAfter['oauth_access_tokens'], $bystanderBefore['oauth_access_tokens']);
    expect($bystanderAfter)->toBe($bystanderBefore);
});

it('H-nexoid-10: every tool the account used gets ONE sub-only logout_token, before the account is gone', function () {
    [$user, , [$events, $links]] = acAccountWithEverything();
    $userId = $user->getKey();
    $sids = IdpSession::query()->where('user_id', $userId)->pluck('sid')->all();

    $seenUser = [];
    Http::fake(function (HttpRequest $request) use ($userId, &$seenUser) {
        // When a tool hears about it, the account must still be there to have
        // been read from (endForAccountDeletion runs first).
        $seenUser[] = DB::table('users')->where('id', $userId)->exists();

        return Http::response('', 200);
    });

    $this->actingAs($user)->delete('/profile', ['delete_password' => 'right-password-1'])->assertRedirect(route('home'));

    $sent = collect(acSentLogoutTokens());
    expect($sent->pluck('url')->sort()->values()->all())->toBe([
        $events->backchannel_logout_uri,
        $links->backchannel_logout_uri,
    ]);
    foreach ($sent as $token) {
        expect($token['claims']['sub'])->toBe($userId);
        expect(array_key_exists('sid', $token['claims']))->toBeFalse('The deletion token must carry the sub only.');
    }
    expect($seenUser)->toBe([true, true]);
    expect(count($sids))->toBe(2);
});

it('H-nexoid-10: the wrong password deletes nothing and tells nobody', function () {
    [$user] = acAccountWithEverything();
    $before = acLeftovers($user);

    $this->actingAs($user)->from('/profile')
        ->delete('/profile', ['delete_password' => 'not-the-password'])
        ->assertRedirect('/profile')
        ->assertSessionHasErrorsIn('deleteAccount', 'delete_password');

    // (the request itself writes this browser's session row: not a leftover)
    expect(array_diff_key(acLeftovers($user), ['sessions' => 0]))->toBe(array_diff_key($before, ['sessions' => 0]));
    expect(acSentLogoutTokens())->toBe([]);
    $this->assertAuthenticatedAs($user, 'web');
});

it('H-nexoid-10: a failure half-way leaves the account whole (one transaction)', function () {
    [$user] = acAccountWithEverything();
    $before = acLeftovers($user);
    User::deleting(fn () => throw new RuntimeException('disk full'));

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->delete('/profile', ['delete_password' => 'right-password-1']))
        ->toThrow(RuntimeException::class, 'disk full');

    // Only the sids ended (the tools were told first, on purpose: signing a
    // person out is harmless); every row of the account is still there.
    expect(array_diff_key(acLeftovers($user), ['sessions' => 0]))->toBe(array_diff_key($before, ['sessions' => 0]));
    expect(acLeftovers($user)['sessions'])->toBeGreaterThanOrEqual($before['sessions']);
});

it('H-nexoid-10: the account does not come back when the browser session closes', function () {
    [$user] = acAccountWithEverything();
    $count = User::query()->count();

    $this->actingAs($user)->delete('/profile', ['delete_password' => 'right-password-1']);

    expect(User::query()->count())->toBe($count - 1);
    expect(User::query()->where('email', 'leaving@example.test')->exists())->toBeFalse();
});

it('H-nexoid-10: it lands on the landing with a notice, signed out', function () {
    [$user] = acAccountWithEverything();

    $this->actingAs($user)->followingRedirects()
        ->delete('/profile', ['delete_password' => 'right-password-1'])
        ->assertOk()
        ->assertSee(__('Your Nexo ID account was deleted.'))
        ->assertSee('data-nexo-sign-in', false);
});

it('H-nexoid-10: endForAccountDeletion ends every sid of the account, and nobody else\'s', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = acSid($user, 1);
    $theirs = acSid($other, 1);

    app(IdpSessions::class)->endForAccountDeletion($user);

    expect(IdpSession::query()->find($mine)->ended_reason)->toBe('account_deleted');
    expect(IdpSession::query()->find($theirs)->ended_at)->toBeNull();
});

it('H-nexoid-06: past five sessions the rest fold away, the current device first and «Activa ahora»', function () {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    foreach (range(1, 7) as $i) {
        acSessionRow($user, "old-row-{$i}", null, 'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0', $i);
    }

    $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

    $fold = (int) strpos($html, 'data-sessions-more');
    expect($fold)->toBeGreaterThan(0);
    expect(substr_count(substr($html, 0, $fold), 'data-session '))->toBe(5);
    expect(substr_count(substr($html, $fold), 'data-session '))->toBe(3);
    expect(strpos($html, 'data-session-current'))->toBeLessThan($fold);
    expect($html)->toContain(e(trans_choice('{1} Show 1 more session|[2,*] Show :count more sessions', 3, ['count' => 3])))
        ->toContain(e(__('Active now')));
});
