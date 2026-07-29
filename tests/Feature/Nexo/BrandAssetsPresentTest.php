<?php

// Guardian: the brand assets derived from the tool's mark exist and are wired
// into the shell. Without this, deleting public/og-image.png (or forgetting to
// run generate-brand-assets.mjs after a mark change) keeps the suite green and
// only shows up as a broken favicon / blank OG card in production.

it('ships the brand assets derived from the tool mark', function () {
    $required = [
        'favicon.ico',
        'favicon.svg',
        'apple-touch-icon.png',
        'icon-192.png',
        'icon-512.png',
        'og-image.png',
        'site.webmanifest',
    ];

    $missing = array_values(array_filter(
        $required,
        fn (string $asset) => ! is_file(public_path($asset))
    ));

    expect($missing)->toBe([], 'Missing brand assets in public/ (run scripts/generate-brand-assets.mjs): '.implode(', ', $missing));
});

it('ships an isotype for every tool in the ecosystem registry', function () {
    /** @var array<string, array{mark?: string}> $tools */
    $tools = config('nexo-ecosystem.tools', []);

    expect($tools)->not->toBeEmpty('The ecosystem registry is empty — the app-switcher would render nothing.');

    $missing = [];
    foreach ($tools as $key => $tool) {
        $mark = $tool['mark'] ?? null;
        if ($mark === null || ! is_file(public_path(ltrim($mark, '/')))) {
            $missing[] = $key.' -> '.($mark ?? 'no mark configured');
        }
    }

    expect($missing)->toBe([], "Ecosystem marks missing from public/ (copy from nexo-brand/marks/):\n".implode("\n", $missing));
});

it('links every generated asset from the shared head, and the manifest points at real files', function () {
    // Narrowed from the template's "serve them over http": Laravel's test client
    // dispatches through the router, and static files under public/ are served by
    // the web server, which the test client never reaches — the HTTP variant would
    // assert 404 on every tool. What can drift and IS checked here: the shell
    // stopping to reference an asset, and the manifest pointing at a missing icon.
    $html = $this->get('/')->assertOk()->getContent();

    foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'site.webmanifest'] as $asset) {
        expect(str_contains($html, $asset))->toBeTrue("The shared head no longer links {$asset}.");
    }
    expect(str_contains($html, 'og-image.png'))->toBeTrue('The SEO block no longer points at the generated OG image.');

    /** @var array{icons?: array<int, array{src?: string}>} $manifest */
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true);

    $broken = array_values(array_filter(
        array_column($manifest['icons'] ?? [], 'src'),
        fn (string $src) => ! is_file(public_path(ltrim(parse_url($src, PHP_URL_PATH) ?: $src, '/')))
    ));

    expect($broken)->toBe([], 'site.webmanifest points at icons that do not exist: '.implode(', ', $broken));
});
