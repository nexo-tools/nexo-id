<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\ClientRepository;

/**
 * The full journey a Nexo tool drives, end to end: a brand-new user signs up,
 * verifies their email, then the tool (a distinct origin) runs authorization
 * code + PKCE, exchanges the code for tokens, and reads userinfo.
 */
it('reference flow: signup -> verify -> authorize -> token -> userinfo', function () {
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Reference Tool',
        redirectUris: ['https://tool.example/callback'],
        confidential: false,
        user: null,
    );

    $verifier = str_repeat('r', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    // 1) Sign up (creates an unverified user, logs them in).
    $this->post('/register', [
        'display_name' => 'Grace Hopper',
        'email' => 'grace@tool.example',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ])->assertRedirect(route('verification.notice'));

    $user = User::firstWhere('email', 'grace@tool.example');
    expect($user->hasVerifiedEmail())->toBeFalse();

    // 2) Verify the email via the signed link.
    $verifyUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);
    $this->actingAs($user)->get($verifyUrl);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // 3) Authorize (silent SSO for a first-party client).
    $location = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://tool.example/callback',
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => 'st',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->headers->get('Location');

    expect((string) $location)->toStartWith('https://tool.example/callback?');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $params);

    // 4) Exchange the code for tokens.
    $tokens = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://tool.example/callback',
        'code_verifier' => $verifier,
        'code' => $params['code'],
    ]);
    $tokens->assertOk();
    expect($tokens->json())->toHaveKeys(['access_token', 'id_token']);

    // 5) Read userinfo with the access token.
    $info = $this->withToken($tokens->json('access_token'))->getJson('/oauth/userinfo');
    $info->assertOk();
    expect($info->json('sub'))->toBe($user->id);
    expect($info->json('email'))->toBe('grace@tool.example');
    expect($info->json('name'))->toBe('Grace Hopper');
});
