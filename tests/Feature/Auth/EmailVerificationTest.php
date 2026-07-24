<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

it('AC-VERIFY-1: verifies the email when the signed link is visited', function () {
    Event::fake();
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('profile.show').'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('AC-VERIFY-2: rejects a tampered hash and leaves the account unverified', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('someone-elses-email@example.test'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('AC-VERIFY-2: rejects an expired verification link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('AC-VERIFY-3: blocks unverified users from the authenticated area', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/profile')->assertRedirect(route('verification.notice'));
});

it('AC-VERIFY-3: lets verified users into the authenticated area', function () {
    $user = User::factory()->create(); // verified by default

    $this->actingAs($user)->get('/profile')->assertOk();
});

it('AC-VERIFY-5: the verification link lifetime follows the configured ttl (NEXO_VERIFICATION_TTL wiring)', function () {
    // Laravel's VerifyEmail notification signs the link for auth.verification.expire minutes.
    config(['auth.verification.expire' => 1]);
    $user = User::factory()->unverified()->create();

    $mail = (new VerifyEmail)->toMail($user);
    $url = $mail->actionUrl;

    // Past the 1-minute window the signed link is stale and rejected.
    $this->travel(2)->minutes();

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('AC-VERIFY-4: rate limits resending the verification email', function () {
    $user = User::factory()->unverified()->create();
    Config::set('mail.default', 'array');

    // The route allows 6 posts/minute; the 7th is throttled.
    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($user)->post('/email/verification-notification')->assertStatus(302);
    }

    $this->actingAs($user)->post('/email/verification-notification')->assertStatus(429);
});
