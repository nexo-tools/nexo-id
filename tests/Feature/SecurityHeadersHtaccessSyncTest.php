<?php

it('AC-SEC-1: the .htaccess CSP matches the middleware CSP exactly', function () {
    // The production CSP the middleware emits (no Vite dev server under testing).
    $middlewareCsp = $this->get('/')->headers->get('Content-Security-Policy');

    // The CSP re-asserted in public/.htaccess for LiteSpeed.
    $htaccess = file_get_contents(public_path('.htaccess'));
    preg_match('/Header always set Content-Security-Policy "([^"]*)"/', $htaccess, $m);
    $htaccessCsp = $m[1] ?? null;

    expect($htaccessCsp)->not->toBeNull();
    expect($htaccessCsp)->toBe($middlewareCsp);
});
