<?php

// Nexo ID's own checks on top of the shared StaticPagesTest and
// DarkModeCoverageTest guardians (templates/nexo-ui/guardians). The guardians are
// copied byte-for-byte, so what this tool asserts beyond them lives here.
//
// Why they matter more here than in a tool: this is the identity provider, so its
// error pages are the ones a person meets mid-login, when they are already unsure
// whether something got hacked, and its privacy page is the one place in the
// ecosystem that says what crosses from the account to a tool.

$codes = [403, 404, 419, 429, 500, 503];

it('builds every error view on the shared shell', function () use ($codes) {
    foreach ($codes as $code) {
        $contents = (string) file_get_contents(resource_path("views/errors/{$code}.blade.php"));

        expect(str_contains($contents, 'x-error-layout'))
            ->toBeTrue("errors/{$code}.blade.php does not use the shared shell — it will render without the Nexo ID chrome.");
    }
});

it('renders every error view, not just the one an http test can trigger', function () use ($codes) {
    // 403/419/429/500/503 cannot be provoked from the test client, so they are the
    // ones that break unnoticed when the shared shell changes. Rendering them
    // directly is what catches a renamed prop or a missing component.
    foreach ($codes as $code) {
        $html = view("errors.{$code}")->render();

        expect($html)->toContain((string) $code, 'nexo-header', 'nexo-footer');
    }
});

it('renders the error and legal shells on the token surfaces', function () {
    // The shells that live outside layouts.app are the ones that silently drift
    // off the token layer, because nothing else renders them.
    foreach (['components/error-layout', 'legal/show'] as $view) {
        $contents = (string) file_get_contents(resource_path("views/{$view}.blade.php"));

        expect($contents)->toMatch(
            '/\b(bg-bg|bg-surface|text-ink|text-muted|@extends)\b/',
            "{$view}.blade.php paints outside the token layer — it will not follow the theme."
        );
    }
});

it('ships a dark palette in the tokens, not just the word «dark»', function () {
    $css = (string) file_get_contents(resource_path('css/nexo-tokens.css'));

    expect(str_contains($css, 'prefers-color-scheme: dark'))->toBeTrue('nexo-tokens.css declares no dark palette.');
});

it('names the claims a tool receives, in every locale', function () {
    // A policy of generic clauses would pass every assertion of StaticPagesTest,
    // so the claims the id_token carries are named — in all three languages,
    // since a translator dropping them is exactly how this drifts. `email` and
    // `name` are ordinary words in prose, so only the two unambiguous ones are
    // asserted.
    foreach (config('nexo.locales') as $locale) {
        $content = require lang_path("{$locale}/legal.php");
        $text = implode(' ', array_column($content['privacy']['sections'], 'p'));

        foreach (['sub', 'email_verified'] as $claim) {
            expect(preg_match('/\b'.$claim.'\b/u', $text) === 1)
                ->toBeTrue("lang/{$locale}/legal.php does not name the `{$claim}` claim shared with the tools.");
        }
    }
});
