<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('AC-REG-1: shows the registration form', function () {
    $this->get('/register')->assertOk()->assertSee('Create your account');
});

it('AC-REG-1: creates an unverified user and queues a verification email', function () {
    Event::fake();

    $response = $this->post('/register', [
        'display_name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ]);

    $response->assertRedirect(route('verification.notice'));

    $user = User::firstWhere('email', 'ada@example.test');
    expect($user)->not->toBeNull();
    expect($user->display_name)->toBe('Ada Lovelace');
    expect($user->email_verified_at)->toBeNull();
    $this->assertAuthenticatedAs($user);

    // The Registered event drives the verification email listener (AC-REG-1).
    Event::assertDispatched(Registered::class);
});

it('AC-REG-2: rejects a duplicate email case-insensitively without a 500', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $response = $this->from('/register')->post('/register', [
        'display_name' => 'Someone',
        'email' => 'TAKEN@Example.TEST',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('email');
    expect(User::where('email', 'taken@example.test')->count())->toBe(1);
});

it('AC-REG-2: normalizes the stored email to lowercase', function () {
    $this->post('/register', [
        'display_name' => 'Grace Hopper',
        'email' => 'Grace.Hopper@Example.TEST',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ]);

    expect(User::firstWhere('email', 'grace.hopper@example.test'))->not->toBeNull();
});

it('AC-REG-3: rejects a password below the minimum length', function () {
    $response = $this->from('/register')->post('/register', [
        'display_name' => 'Short Pass',
        'email' => 'short@example.test',
        'password' => 'abc',
        'password_confirmation' => 'abc',
    ]);

    $response->assertSessionHasErrors('password');
    expect(User::count())->toBe(0);
});

it('AC-REG-3: rejects an unconfirmed password', function () {
    $response = $this->from('/register')->post('/register', [
        'display_name' => 'Mismatch',
        'email' => 'mismatch@example.test',
        'password' => 'correct-horse',
        'password_confirmation' => 'different-horse',
    ]);

    $response->assertSessionHasErrors('password');
    expect(User::count())->toBe(0);
});

it('AC-REG-4: stores the password as a verifiable hash, never plaintext', function () {
    $this->post('/register', [
        'display_name' => 'Alan Turing',
        'email' => 'alan@example.test',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ]);

    $user = User::firstWhere('email', 'alan@example.test');
    expect($user->password)->not->toBe('correct-horse');
    expect(Hash::check('correct-horse', $user->password))->toBeTrue();
});

it('AC-REG-5: rate limits registration attempts per IP', function () {
    // The route allows 10 posts/minute; the 11th is throttled. Invalid payloads
    // keep the client unauthenticated so every attempt reaches the throttle
    // (a successful register would log in and the guest middleware would divert
    // later attempts before the limiter counted them).
    for ($i = 0; $i < 10; $i++) {
        $this->post('/register', [])->assertStatus(302);
    }

    $this->post('/register', [])->assertStatus(429);
});
