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

it('ships the template emitter in the app bundle, honouring Do Not Track', function () {
    // The transport (fetch text/plain, no credentials, keepalive — no CORS
    // preflight) is pinned by BeaconClientTest; this only keeps the privacy
    // switches and the payload shape in sight. The file is a byte-for-byte
    // copy of templates/nexo-ui/beacon/nexo-beacon.js.
    expect(file_get_contents(resource_path('js/nexo-beacon.js')))
        ->toContain('doNotTrack')            // honours DNT
        ->toContain('globalPrivacyControl')  // honours GPC
        ->toContain("event: 'pageview'");    // documented payload shape

    expect(file_get_contents(resource_path('js/app.js')))->toContain('nexo-beacon.js');
});
