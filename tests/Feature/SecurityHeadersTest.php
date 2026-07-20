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
        ->toContain("form-action 'self'")
        ->toContain("script-src 'self'");

    // No external host leaks into the policy — every source is self/data/inline.
    expect($csp)->not->toContain('http://');
    expect($csp)->not->toContain('https://');
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
