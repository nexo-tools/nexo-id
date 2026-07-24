<?php

it('serves meta description, canonical and open graph tags on the home page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="description"')
        ->toContain('<link rel="canonical" href="'.url('/').'"')
        ->toContain('<meta property="og:title"')
        ->toContain('<meta property="og:url" content="'.url('/').'"')
        ->toContain('<meta name="theme-color"')
        ->toContain('hreflang="es"')
        ->toContain('hreflang="en"')
        ->toContain('hreflang="pt"')
        ->toContain('hreflang="x-default"')
        // The shared component's doc comment must stay a comment (no leaked literal
        // props) and prop values must be escaped exactly once (no double-encoding).
        ->not->toContain(':hreflang=')
        ->not->toContain(':noindex=')
        ->not->toContain('&amp;#0');
});

it('marks auth pages as noindex', function () {
    $this->get('/login')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
    $this->get('/register')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
});

it('serves robots.txt with the private surface disallowed and a sitemap pointer', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())
        ->toContain('User-agent: *')
        ->toContain('Disallow: /profile')
        ->toContain('Disallow: /oauth/')
        ->toContain('Sitemap: '.route('sitemap'));
});

it('serves a valid sitemap.xml listing the public home page', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()->assertHeader('Content-Type', 'application/xml');
    expect($response->getContent())
        ->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->toContain('<loc>'.url('/').'</loc>');
});
