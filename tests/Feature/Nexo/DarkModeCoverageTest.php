<?php

// Guardian: dark mode is a first-class theme, not an afterthought on the home page.
//
// Two things are checked, because they fail in different ways:
//   1. The theme plumbing: <html> gets data-theme before paint, and the tokens
//      file declares both palettes. Without this the page flashes the wrong theme.
//   2. Coverage: the key views do not paint surfaces with theme-blind utilities.
//      A view that hardcodes `bg-white` looks fine in light and breaks in dark —
//      exactly the drift NoHardcodedColorsTest cannot see (it only scans hex).

use RecursiveDirectoryIterator as Dir;
use RecursiveIteratorIterator as Walk;

it('stamps the theme before paint and ships both palettes', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(str_contains($html, 'data-theme'))->toBeTrue('The theme-init snippet must stamp data-theme on <html> before paint.');

    $tokens = resource_path('css/nexo-tokens.css');
    expect(is_file($tokens))->toBeTrue('nexo-tokens.css is missing — the tool is not on the brand tokens.');

    $css = file_get_contents($tokens);
    expect(str_contains($css, '--nexo-'))->toBeTrue('Token variables missing from nexo-tokens.css.');
    expect(str_contains($css, 'prefers-color-scheme: dark'))->toBeTrue('nexo-tokens.css declares no dark palette.');
});

it('paints the key views through the token layer, so they work in both themes', function () {
    // Utilities that hardcode a light-only surface, bypassing the tokens.
    $themeBlind = ['bg-white', 'bg-gray-50', 'bg-gray-100', 'text-black'];

    // Every view of this tool: the shells, the shared chrome, and the pages a user
    // meets while signed out (auth + errors + legal), which are the ones that get
    // written once and never reopened in dark mode.
    $keyViews = [resource_path('views')];

    $offenders = [];
    foreach (array_filter($keyViews, 'file_exists') as $path) {
        $files = is_dir($path)
            ? iterator_to_array(new Walk(new Dir($path, FilesystemIterator::SKIP_DOTS)))
            : [new SplFileInfo($path)];

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            foreach ($themeBlind as $utility) {
                // Allowed when paired with a dark: variant on the same element.
                if (preg_match('/\b'.preg_quote($utility, '/').'\b(?![^"\']*dark:)/', $contents)) {
                    $offenders[] = $file->getPathname().' -> '.$utility;
                }
            }
        }
    }

    expect($offenders)->toBe([], "Theme-blind utilities found (use token classes like bg-bg/bg-surface, or pair with dark:):\n".implode("\n", $offenders));
});

it('renders the error and legal shells on the token surfaces', function () {
    // The shells that live outside layouts.app are the ones that silently drift
    // off the token layer, because nothing else renders them.
    foreach (['components/error-layout', 'legal/show'] as $view) {
        $contents = file_get_contents(resource_path("views/{$view}.blade.php"));

        expect($contents)->toMatch(
            '/\b(bg-bg|bg-surface|text-ink|text-muted|@extends)\b/',
            "{$view}.blade.php paints outside the token layer — it will not follow the theme."
        );
    }
});
