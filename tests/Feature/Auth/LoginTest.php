<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('AC-LOGIN-1: shows the login form', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in to your account');
});

it('AC-LOGIN-1: authenticates with valid credentials and redirects to the account', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-horse',
    ]);

    $response->assertRedirect(route('profile.show'));
    $this->assertAuthenticatedAs($user);
});

it('AC-LOGIN-2: returns identical generic errors for unknown email and wrong password', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $wrongPassword = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ]);

    $unknownEmail = $this->from('/login')->post('/login', [
        'email' => 'nobody@example.test',
        'password' => 'whatever-value',
    ]);

    $wrongPassword->assertRedirect('/login')->assertSessionHasErrors('email');
    $unknownEmail->assertRedirect('/login')->assertSessionHasErrors('email');

    // Same message in both cases — no way to tell which factor was wrong.
    expect($unknownEmail->getSession()->get('errors')->get('email'))
        ->toBe($wrongPassword->getSession()->get('errors')->get('email'))
        ->toBe([__('auth.failed')]);

    $this->assertGuest();
});

it('AC-LOGIN-3: locks the credential out after five failed attempts (email+ip)', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    // Even the correct password is now rejected with a throttle message.
    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'correct-horse',
    ]);

    $response->assertSessionHasErrors('email');
    expect($response->getSession()->get('errors')->get('email')[0])
        ->toContain('Too many login attempts');
    $this->assertGuest();

    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
});

it('AC-LOGIN-4: regenerates the session id on login (no fixation)', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $this->get('/login'); // establish a session
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse']);

    expect(session()->getId())->not->toBe($before);
});

it('AC-LOGIN-5: logout invalidates the session and rotates the csrf token', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $tokenBefore = csrf_token();

    $this->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
    expect(csrf_token())->not->toBe($tokenBefore);
});

it('AC-LOGIN-6: session cookie is http-only and same-site lax', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-horse',
    ]);

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull();
    expect($cookie->isHttpOnly())->toBeTrue();
    expect(strtolower($cookie->getSameSite()))->toBe('lax');
});
