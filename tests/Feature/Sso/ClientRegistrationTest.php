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
