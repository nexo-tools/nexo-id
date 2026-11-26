<?php

// «Remember me» (ADR-010, D4): 30 days instead of the framework's ~400, the
// cookies issued under the old policy are cut, and every revocation rotates the
// token so a revoked device cannot come back through its cookie.

use App\Auth\IdpSessions;
use App\Models\IdpSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function rememberCookieName(): string
{
    return auth()->guard('web')->getRecallerName();
}

function rememberCookieFor(User $user): string
{
    return $user->getKey().'|'.$user->getRememberToken().'|'.auth()->guard('web')->hashPasswordForCookie($user->getAuthPassword());
}

it('AC-REMEMBER-1: the «remember me» cookie lasts 30 days', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret-password', 'remember' => 'on']);

    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === rememberCookieName());
    expect($cookie)->not->toBeNull();

    $days = ($cookie->getExpiresTime() - time()) / 86400;
    expect($days)->toBeGreaterThan(29.9)->toBeLessThanOrEqual(30.0);
    expect(config('auth.guards.web.remember'))->toBe(60 * 24 * 30);
});

it('AC-REMEMBER-2: nexo:rotate-remember-tokens cuts every cookie, says how many, and is idempotent', function () {
    $users = User::factory()->count(3)->create();
    $users[0]->forceFill(['remember_token' => Str::random(60)])->save();
    $users[1]->forceFill(['remember_token' => Str::random(60)])->save();
    $users[2]->forceFill(['remember_token' => null])->save(); // never ticked the box
    $cookie = rememberCookieFor($users[0]->fresh());

    $this->artisan('nexo:rotate-remember-tokens')->expectsOutput('Rotated 2 remember token(s).')->assertSuccessful();
    $this->artisan('nexo:rotate-remember-tokens')->expectsOutput('Rotated 0 remember token(s).')->assertSuccessful();

    expect(DB::table('users')->whereNotNull('remember_token')->count())->toBe(0);

    // The old cookie no longer signs anybody in.
    $this->withCookie(rememberCookieName(), $cookie)->get('/profile')->assertRedirect(route('login'));
    $this->assertGuest('web');
});

it('AC-REMEMBER-2: the deploy migration performs the same one-time cut', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => Str::random(60)])->save();

    (require database_path('migrations/2026_11_26_000003_cut_remember_tokens_issued_under_the_400_day_policy.php'))->up();

    expect(DB::table('users')->where('id', $user->getKey())->value('remember_token'))->toBeNull();
});

it('AC-REMEMBER-3: «Cerrar esta sesión» rotates the token and keeps this device remembered', function () {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => $old = Str::random(60)])->save();
    DB::table('sessions')->insert([
        'id' => 'other-device', 'user_id' => $user->getKey(), 'ip_address' => null, 'user_agent' => null,
        'payload' => base64_encode('{}'), 'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($user)
        ->withCookie(rememberCookieName(), rememberCookieFor($user))
        ->delete(route('sessions.destroy', 'other-device'));

    $fresh = $user->fresh();
    expect($fresh->getRememberToken())->not->toBe($old)->toHaveLength(60);
    // This device got its cookie back, bound to the new token.
    expect($response->getCookie(rememberCookieName())?->getValue())->toBe(rememberCookieFor($fresh));
});

it('AC-REMEMBER-3: «Cerrar las demás sesiones» rotates the token; a device without the cookie gets none', function () {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => $old = Str::random(60)])->save();
    $oldCookie = rememberCookieFor($user);

    $response = $this->actingAs($user)->delete(route('sessions.destroy-others'));

    expect($user->fresh()->getRememberToken())->not->toBe($old);
    expect($response->getCookie(rememberCookieName()))->toBeNull();

    // The revoked device's cookie is dead.
    auth()->guard('web')->logout();
    app('session.store')->flush();
    $this->withCookie(rememberCookieName(), $oldCookie)->get('/profile')->assertRedirect(route('login'));
});

it('AC-REMEMBER-3: «Cerrar sesión en todos los dispositivos» and the password change rotate it too', function () {
    $user = User::factory()->create(['password' => 'old-password-1']);
    $user->forceFill(['remember_token' => $old = Str::random(60)])->save();

    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => 'old-password-1',
        'password' => 'new-password-2',
        'password_confirmation' => 'new-password-2',
    ]);
    $afterPassword = $user->fresh()->getRememberToken();
    expect($afterPassword)->not->toBe($old);

    $this->actingAs($user->fresh())->post(route('logout.everywhere'));
    expect($user->fresh()->getRememberToken())->not->toBe($afterPassword);
});

it('AC-REMEMBER-3: an ended sid with a live cookie does not come back as the same session', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => Str::random(60)])->save();

    // Recaller login → a sid; then that sid is ended from another device.
    $this->withCookie(rememberCookieName(), rememberCookieFor($user))->get('/profile')->assertOk();
    $sid = session('nexo_idp.sid');
    app(IdpSessions::class)->end([$sid], 'session_revoked');

    $this->get('/profile')->assertRedirect(route('login'));
    expect(IdpSession::query()->find($sid)->ended_reason)->toBe('session_revoked');
});
