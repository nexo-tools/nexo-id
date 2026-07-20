<?php

use App\Http\Controllers\SessionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('session.driver', 'database');
});

/**
 * Seed a session row for the given user.
 */
function seedSession(User $user, string $id, string $ip = '10.0.0.1', string $ua = 'Test Browser'): void
{
    DB::table('sessions')->updateOrInsert(
        ['id' => $id],
        [
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ],
    );
}

/**
 * A request carrying a session with a deterministic id, so "current session"
 * detection is exact in the revoke tests (the HTTP test client's session id is
 * not stable across method changes).
 */
function requestAsCurrentSession(User $user, string $sessionId): Request
{
    $store = new Store(config('session.cookie'), new ArraySessionHandler(120), $sessionId);
    $store->start();

    $request = Request::create('/profile/sessions', 'DELETE');
    $request->setLaravelSession($store);
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('AC-SESS-1: lists active sessions and flags the current device', function () {
    $user = User::factory()->create();
    seedSession($user, 'other-session-1', '203.0.113.5', 'Other Browser');

    // show() saves the current session, so it appears and is flagged.
    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk()
        ->assertSee('203.0.113.5')   // the other session is listed
        ->assertSee('This device');   // the current one is flagged

    expect(substr_count($response->getContent(), 'This device'))->toBe(1);
});

it('AC-SESS-2: revokes a specific other session (over http)', function () {
    $user = User::factory()->create();
    seedSession($user, 'kill-me', '203.0.113.9');

    $this->actingAs($user)->delete('/profile/sessions/kill-me')->assertRedirect();

    expect(DB::table('sessions')->where('id', 'kill-me')->exists())->toBeFalse();
});

it('AC-SESS-2: cannot revoke another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    seedSession($other, 'victim-session', '203.0.113.20');

    $this->actingAs($user)->delete('/profile/sessions/victim-session')->assertRedirect();

    // The other user's session is untouched (scoped to the current user).
    expect(DB::table('sessions')->where('id', 'victim-session')->exists())->toBeTrue();
});

it('AC-SESS-2: keeps the current session when revoking a specific one', function () {
    $user = User::factory()->create();
    $currentId = str_repeat('a', 40);
    seedSession($user, $currentId, '127.0.0.1');
    seedSession($user, 'other-1', '203.0.113.1');

    (new SessionController)->destroy(requestAsCurrentSession($user, $currentId), 'other-1');

    expect(DB::table('sessions')->where('id', 'other-1')->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', $currentId)->exists())->toBeTrue();
});

it('AC-SESS-3: revokes all other sessions but keeps the current one', function () {
    $user = User::factory()->create();
    $currentId = str_repeat('a', 40);
    seedSession($user, $currentId, '127.0.0.1');
    seedSession($user, 'other-a', '203.0.113.1');
    seedSession($user, 'other-b', '203.0.113.2');

    (new SessionController)->destroyOthers(requestAsCurrentSession($user, $currentId));

    $remaining = DB::table('sessions')->where('user_id', $user->id)->pluck('id');
    expect($remaining)->toContain($currentId);
    expect($remaining)->not->toContain('other-a');
    expect($remaining)->not->toContain('other-b');
});
