<?php

// The cookieless beacon emitter is wired into nexoid: it fires only when this
// instance opts in (NEXO_BEACON_ENABLED) and respects Do Not Track. Mirrors the
// nexotools reference (AC-BEACON-8).

it('renders the beacon emitter metas only when the beacon is enabled', function () {
    config(['nexo.beacon.enabled' => true]);

    $this->get('/')
        ->assertSee('name="nexo:beacon-endpoint"', false)
        ->assertSee('name="nexo:beacon-origin" content="nexoid"', false);
});

it('renders the beacon metas on the auth layout too when enabled', function () {
    config(['nexo.beacon.enabled' => true]);

    $this->get('/login')
        ->assertSee('name="nexo:beacon-origin" content="nexoid"', false);
});

it('renders no emitter metas when the beacon is off (default/standalone)', function () {
    config(['nexo.beacon.enabled' => false]);

    $this->get('/')
        ->assertDontSee('nexo:beacon-endpoint', false)
        ->assertDontSee('nexo:beacon-origin', false);
});

it('ships the shareable snippet in the app bundle, honouring Do Not Track', function () {
    $source = file_get_contents(resource_path('js/nexo-beacon.js'));

    expect($source)
        ->toContain('doNotTrack')            // honours DNT
        ->toContain('globalPrivacyControl')  // honours GPC
        ->toContain('sendBeacon')            // non-blocking send
        ->toContain("event: 'pageview'");    // documented payload shape

    expect(file_get_contents(resource_path('js/app.js')))->toContain('nexo-beacon.js');
});
