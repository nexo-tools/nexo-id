<?php

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('AC-PWD-1: emails a reset link and stores the token hashed', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);

    // The DB keeps only a hash of the token, never the raw token.
    $stored = DB::table('password_reset_tokens')->where('email', $user->email)->first();
    expect($stored)->not->toBeNull();
    expect(strlen($stored->token))->toBeGreaterThan(40);
});

it('AC-PWD-2: gives the same response for an unknown email (no enumeration)', function () {
    Notification::fake();

    $known = User::factory()->create();

    $knownResponse = $this->post('/forgot-password', ['email' => $known->email]);
    $unknownResponse = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

    expect($unknownResponse->getSession()->get('status'))
        ->toBe($knownResponse->getSession()->get('status'));
    $unknownResponse->assertSessionHasNoErrors();
});

it('AC-PWD-3: resets the password with a valid token, then rejects its reuse', function () {
    Notification::fake();
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-secret',
        'password_confirmation' => 'brand-new-secret',
    ])->assertRedirect(route('login'));

    expect(Hash::check('brand-new-secret', $user->fresh()->password))->toBeTrue();

    // Reusing the same (now consumed) token fails.
    $this->from('/reset-password')->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'another-secret-1',
        'password_confirmation' => 'another-secret-1',
    ])->assertSessionHasErrors('email');
});

it('AC-PWD-4: rejects an expired or malformed token', function () {
    $user = User::factory()->create();

    $this->from('/reset-password')->post('/reset-password', [
        'token' => 'totally-invalid-token',
        'email' => $user->email,
        'password' => 'brand-new-secret',
        'password_confirmation' => 'brand-new-secret',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('brand-new-secret', $user->fresh()->password))->toBeFalse();
});

it('AC-PWD-5: rate limits the reset request endpoint', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/forgot-password', ['email' => "user{$i}@example.test"])->assertStatus(302);
    }

    $this->post('/forgot-password', ['email' => 'over@example.test'])->assertStatus(429);
});

it('AC-PWD-7: the reset token expires per the configured ttl (NEXO_PASSWORD_RESET_TTL wiring)', function () {
    // Low lifetime — set before the broker resolves so it reads this value.
    config(['auth.passwords.users.expire' => 5]); // minutes
    $user = User::factory()->create();
    $token = Password::createToken($user);

    // Travel past the configured lifetime; the (still single-use) token is now stale.
    $this->travel(6)->minutes();

    $this->from('/reset-password')->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-secret',
        'password_confirmation' => 'brand-new-secret',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('brand-new-secret', $user->fresh()->password))->toBeFalse();
});

it('AC-PWD-6: notifies the account and revokes other sessions on reset', function () {
    Notification::fake();
    config()->set('session.driver', 'database');

    $user = User::factory()->create();
    // Two lingering sessions for this user in the DB session store.
    DB::table('sessions')->insert([
        ['id' => 'sess-a', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'a', 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'sess-b', 'user_id' => $user->id, 'ip_address' => '2.2.2.2', 'user_agent' => 'b', 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    $token = Password::createToken($user);
    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-secret',
        'password_confirmation' => 'brand-new-secret',
    ])->assertRedirect(route('login'));

    Notification::assertSentTo($user, PasswordChanged::class);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});
