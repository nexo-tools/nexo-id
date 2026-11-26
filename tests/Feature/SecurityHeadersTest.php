<?php

it('AC-SEC-1: sends the security headers on every response', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('AC-SEC-1: sends a self-contained content-security-policy with no external hosts', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self' ")
        ->toContain("script-src 'self'");

    // External hosts appear in exactly two places: the Nexo Tools hub in
    // connect-src (opt-in cookieless beacon) and the ecosystem's tools in
    // form-action (ADR-010 — see AC-CSP-1). Nowhere else.
    expect($csp)->toContain("connect-src 'self' https://nexotools.alvarocdev.com");

    $directives = collect(explode(';', (string) $csp))->map(fn (string $d): string => trim($d))
        ->reject(fn (string $d): bool => str_starts_with($d, 'connect-src') || str_starts_with($d, 'form-action'))
        ->implode('; ');

    expect($directives)->not->toContain('http://')->not->toContain('https://');
});

it('AC-SEC-1: connect-src follows the configured beacon endpoint, not a hard-coded hub', function () {
    // A self-hosted (or local e2e) hub: the beacon posts to NEXO_BEACON_ENDPOINT, so
    // that is the origin the browser must be allowed to reach — and only that one.
    config(['nexo.beacon.endpoint' => 'http://nexotools.localhost:9080/beacon']);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
    preg_match('/connect-src ([^;]+)/', $csp, $m);

    expect(trim($m[1] ?? ''))->toBe("'self' http://nexotools.localhost:9080");
});

it('AC-SEC-1: connect-src stays self-only when the beacon endpoint is not an http(s) URL', function () {
    config(['nexo.beacon.endpoint' => 'javascript:alert(1)']);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
    preg_match('/connect-src ([^;]+)/', $csp, $m);

    expect(trim($m[1] ?? ''))->toBe("'self'");
});

it('AC-SEC-2: does not advertise HSTS over plain http', function () {
    $response = $this->get('http://localhost/');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('AC-SEC-2: advertises HSTS over https', function () {
    $response = $this->get('https://localhost/');

    expect($response->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=31536000');
});

it('AC-CSP-1: form-action allows the origin of every other tool in the ecosystem registry', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
    preg_match('/form-action ([^;]+)/', $csp, $m);
    $sources = explode(' ', trim($m[1] ?? ''));

    // The sign-in POST redirects to /oauth/authorize and from there to the
    // tool's callback; form-action governs that whole chain (ADR-010).
    $expected = collect(config('nexo-ecosystem.tools'))
        ->except(config('nexo-ecosystem.current'))
        ->map(fn (array $tool): string => (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $tool['url']))
        ->values()
        ->all();

    expect($sources[0])->toBe("'self'");
    expect(array_slice($sources, 1))->toBe($expected);
    expect($expected)->toBe([
        'https://nexotools.alvarocdev.com',
        'https://nexolinks.alvarocdev.com',
        'https://nexoagenda.alvarocdev.com',
        'https://nexoshort.alvarocdev.com',
        'https://nexoevents.alvarocdev.com',
    ]);
    // Nexo ID itself is covered by 'self', never listed.
    expect($sources)->not->toContain('https://nexoid.alvarocdev.com');
});

it('AC-CSP-1: form-action follows the registry when a self-host points it elsewhere', function () {
    config()->set('nexo-ecosystem.tools.nexoevents.url', 'http://events.localhost:8103/some/path');

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain('http://events.localhost:8103')
        ->not->toContain('https://nexoevents.alvarocdev.com')
        ->not->toContain('/some/path');
});
