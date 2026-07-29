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

it('serves a valid sitemap.xml listing every public page', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()->assertHeader('Content-Type', 'application/xml');
    expect($response->getContent())
        ->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->toContain('<loc>'.url('/').'</loc>')
        // Public and indexable, so they belong here: help is the contact surface
        // and the legal pages are the ones a user (or a store review) looks for.
        ->toContain('<loc>'.route('help').'</loc>')
        ->toContain('<loc>'.route('legal.privacy').'</loc>')
        ->toContain('<loc>'.route('legal.terms').'</loc>');
});

it('serves the public pages with their own SEO head', function () {
    foreach ([route('help'), route('legal.privacy'), route('legal.terms')] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)
            ->toContain('<meta name="description"')
            ->toContain('<link rel="canonical" href="'.$url.'"')
            // Listed in the sitemap, so it must not tell robots to skip it.
            ->not->toContain('<meta name="robots" content="noindex"');
    }
});

it('emits JSON-LD that actually parses', function () {
    // The block used to render compiled Blade internals instead of JSON: keys
    // like `@context` are Blade directives (Laravel 11 added `@context`), so the
    // template was compiling them away and shipping broken structured data on
    // every page. Asserting the tag exists is not enough — it has to parse.
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    expect($matches[1])->not->toBeEmpty('No JSON-LD block was rendered.');

    foreach ($matches[1] as $block) {
        $decoded = json_decode($block, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE, 'JSON-LD is not valid JSON: '.substr($block, 0, 200));
        expect($decoded['@context'] ?? null)->toBe('https://schema.org');
        expect($decoded['@type'] ?? null)->not->toBeNull('JSON-LD has no @type.');
    }
});
