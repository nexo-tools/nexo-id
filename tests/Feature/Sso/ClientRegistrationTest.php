<?php

use App\Models\OauthClient;
use App\Models\User;

it('AC-CLIENT-1: registers a first-party public client via the console command', function () {
    $this->artisan('nexo:sso-client', [
        'name' => 'Nexo Short',
        '--redirect' => ['https://nxo.li/auth/callback'],
    ])->assertSuccessful();

    $client = OauthClient::query()->firstWhere('name', 'Nexo Short');

    expect($client)->not->toBeNull();
    expect($client->firstParty())->toBeTrue();          // no owner
    expect($client->secret)->toBeNull();                 // public (PKCE) client
    expect($client->redirect_uris)->toContain('https://nxo.li/auth/callback');
    expect($client->hasGrantType('authorization_code'))->toBeTrue();
});

it('AC-CLIENT-1: requires at least one redirect uri', function () {
    $this->artisan('nexo:sso-client', ['name' => 'No Redirect'])->assertFailed();

    expect(OauthClient::query()->where('name', 'No Redirect')->exists())->toBeFalse();
});

it('AC-CLIENT-1: a first-party client skips the consent screen', function () {
    $client = new OauthClient(['name' => 'First Party']);
    // No owner set -> first party -> consent-free.
    $user = User::factory()->make();

    expect($client->skipsAuthorization($user, []))->toBeTrue();
});

it('AC-CLIENT-3: nexo:sso-client registers the back-channel logout URI with the client', function () {
    $this->artisan('nexo:sso-client', [
        'name' => 'Nexo Events',
        '--redirect' => ['https://events.test/auth/nexo/callback'],
        '--backchannel' => 'https://events.test/auth/nexo/backchannel-logout',
    ])->expectsOutputToContain('backchannel: https://events.test/auth/nexo/backchannel-logout')->assertSuccessful();

    expect(OauthClient::query()->firstWhere('name', 'Nexo Events')->backchannel_logout_uri)
        ->toBe('https://events.test/auth/nexo/backchannel-logout');

    $this->artisan('nexo:sso-client', [
        'name' => 'Bad URI',
        '--redirect' => ['https://bad.test/callback'],
        '--backchannel' => 'not-a-url',
    ])->assertFailed();

    expect(OauthClient::query()->where('name', 'Bad URI')->exists())->toBeFalse();
});

it('AC-CLIENT-4: nexo:sso-client:update sets the back-channel URI and adds redirects, idempotently', function () {
    $this->artisan('nexo:sso-client', ['name' => 'Nexo Links', '--redirect' => ['https://links.test/auth/nexo/callback']]);
    $client = OauthClient::query()->firstWhere('name', 'Nexo Links');

    $args = [
        'client_id' => $client->getKey(),
        '--backchannel' => 'https://links.test/auth/nexo/backchannel-logout',
        '--add-redirect' => ['https://links.test/auth/nexo/signed-out'],
    ];

    $this->artisan('nexo:sso-client:update', $args)
        ->expectsOutput('Client updated.')
        ->expectsOutputToContain('redirects: https://links.test/auth/nexo/callback, https://links.test/auth/nexo/signed-out')
        ->expectsOutputToContain('backchannel: https://links.test/auth/nexo/backchannel-logout')
        ->assertSuccessful();

    // Same call again: nothing changes, the final state is printed anyway.
    $this->artisan('nexo:sso-client:update', $args)
        ->expectsOutput('Nothing to change.')
        ->expectsOutputToContain('backchannel: https://links.test/auth/nexo/backchannel-logout')
        ->assertSuccessful();

    $client->refresh();
    expect($client->redirect_uris)->toBe(['https://links.test/auth/nexo/callback', 'https://links.test/auth/nexo/signed-out']);
    expect($client->backchannel_logout_uri)->toBe('https://links.test/auth/nexo/backchannel-logout');

    $this->artisan('nexo:sso-client:update', ['client_id' => $client->getKey(), '--remove-backchannel' => true])
        ->expectsOutputToContain('backchannel: (none)')
        ->assertSuccessful();
    expect($client->fresh()->backchannel_logout_uri)->toBeNull();
});

it('AC-CLIENT-4: nexo:sso-client:update refuses an unknown client and invalid URIs', function () {
    $this->artisan('nexo:sso-client:update', ['client_id' => '00000000-0000-0000-0000-000000000000'])->assertFailed();

    $this->artisan('nexo:sso-client', ['name' => 'Nexo Short', '--redirect' => ['https://short.test/cb']]);
    $client = OauthClient::query()->firstWhere('name', 'Nexo Short');

    $this->artisan('nexo:sso-client:update', ['client_id' => $client->getKey(), '--add-redirect' => ['javascript:alert(1)']])->assertFailed();
    $this->artisan('nexo:sso-client:update', ['client_id' => $client->getKey(), '--backchannel' => 'ftp://short.test/bc'])->assertFailed();

    expect($client->fresh()->redirect_uris)->toBe(['https://short.test/cb']);
    expect($client->fresh()->backchannel_logout_uri)->toBeNull();
});
