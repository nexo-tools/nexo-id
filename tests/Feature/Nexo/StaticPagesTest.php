<?php

use Illuminate\Support\Facades\Route;

// Guardian: the static surfaces every Nexo tool must have — error pages with the
// tool's identity, and legal pages — exist, answer, and are translated.
//
// Why it exists: 404/419/429/500 and privacy/terms are the pages nobody opens
// while building, so they rot silently. 419 and 429 in particular are the ones a
// real user hits (expired session, rate limit) and the ones most often left as
// Laravel's untranslated default. Here they matter more than in a normal tool:
// this is the identity provider, so its error pages are the ones a user meets
// mid-login, when they are already unsure whether something got hacked.
//
// Note on style: Pest's toContain() takes needles, not a message, so the checks
// that need to explain themselves assert on str_contains() via toBeTrue($why).

$codes = [403, 404, 419, 429, 500, 503];

it('ships an error view for every code the standard requires', function () use ($codes) {
    $missing = array_values(array_filter(
        $codes,
        fn (int $code) => ! is_file(resource_path("views/errors/{$code}.blade.php"))
    ));

    expect($missing)->toBe([], 'Missing error views (copy from templates/nexo-ui/pages/errors/): '.implode(', ', $missing));
});

it('renders error pages with the tool chrome and no untranslated placeholders', function () use ($codes) {
    foreach ($codes as $code) {
        $contents = file_get_contents(resource_path("views/errors/{$code}.blade.php"));

        // Strings go through __() so the generator can translate them.
        expect(str_contains($contents, '__('))->toBeTrue("errors/{$code}.blade.php has hardcoded strings — wrap them in __().");
        expect(str_contains($contents, 'x-error-layout'))->toBeTrue("errors/{$code}.blade.php does not use the shared shell — it will render without the Nexo ID chrome.");
        expect($contents)->not->toContain('[COMPLETAR');
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

it('serves a branded 404 instead of the framework default', function () {
    $html = $this->get('/this-path-does-not-exist-'.uniqid())
        ->assertNotFound()
        ->getContent();

    // The chrome renders, so the page belongs to the product.
    expect($html)->toContain('404', 'nexo-header', 'nexo-footer');
    expect($html)->not->toContain('Whoops, looks like something went wrong');
});

it('serves the legal pages and links them from each other', function () {
    foreach (['legal.privacy', 'legal.terms'] as $route) {
        expect(Route::has($route))->toBeTrue("Route {$route} is not registered (see templates/nexo-ui/pages/legal/routes-snippet.php).");

        $html = $this->get(route($route))->assertOk()->getContent();

        // A surviving placeholder means the tool shipped the template, not its content.
        expect($html)->not->toContain('[COMPLETAR');
        expect($html)->toContain(route('legal.privacy'), route('legal.terms'));
    }
});

it('translates the legal content in every supported locale', function () {
    foreach (config('nexo.locales') as $locale) {
        $path = lang_path("{$locale}/legal.php");
        expect(is_file($path))->toBeTrue("lang/{$locale}/legal.php is missing.");

        $content = require $path;

        foreach (['privacy', 'terms'] as $page) {
            expect($content[$page]['title'] ?? null)->not->toBeNull("legal.{$page}.title missing in {$locale}.");
            expect($content[$page]['sections'] ?? [])->not->toBeEmpty("legal.{$page}.sections empty in {$locale}.");
        }

        expect(json_encode($content))->not->toContain('[COMPLETAR');
    }
});

it('names the claims a tool receives, in every locale', function () {
    // Nexo ID's privacy page is the ONE place in the ecosystem that documents what
    // crosses from the account to a tool. A policy of generic clauses would pass
    // every other assertion in this file, so the claims the id_token carries are
    // named — in all three languages, since a translator dropping them is exactly
    // how this drifts. `email` and `name` are ordinary words in prose, so only the
    // two unambiguous ones are asserted.
    foreach (config('nexo.locales') as $locale) {
        $content = require lang_path("{$locale}/legal.php");
        $text = implode(' ', array_column($content['privacy']['sections'], 'p'));

        foreach (['sub', 'email_verified'] as $claim) {
            expect(preg_match('/\b'.$claim.'\b/u', $text) === 1)
                ->toBeTrue("lang/{$locale}/legal.php does not name the `{$claim}` claim shared with the tools.");
        }
    }
});

it('names the instance operator on the legal pages when configured', function () {
    // The env-driven operator/contact contract (templates/nexo-ui/pages): with
    // the values set, the pages must say who answers for this instance. One
    // tool shipped legal pages that never consumed these values while the
    // deploy pipeline delivered them — a no-op that read as done. Asserting the
    // rendered output is what catches that class of gap.
    config()->set('nexo.legal.operator', 'Example Operator');
    config()->set('nexo.legal.contact', 'legal@example.test');

    $html = $this->get(route('legal.privacy'))->assertOk()->getContent();

    expect(str_contains($html, 'Example Operator'))->toBeTrue('The operator section did not render.');
    expect(str_contains($html, 'legal@example.test'))->toBeTrue('The contact did not render.');
});
