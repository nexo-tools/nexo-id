<?php

// The help center says what the product does (H-nexoid-11). It used to promise
// «una pantalla de consentimiento» every time a tool signs in, and the Nexo
// tools never show one: they are first-party (owner-less) clients, and
// OauthClient::skipsAuthorization() lets them straight in. The answers also
// name the account screens by the labels the profile really shows.

use App\Models\OauthClient;
use App\Models\User;
use Laravel\Passport\ClientRepository;

/** Every help answer of a locale, joined. */
function helpAnswers(string $locale): string
{
    return implode("\n", array_map(
        fn (array $faq): string => $faq['q'].' '.$faq['a'],
        (array) __('help.faqs', [], $locale),
    ));
}

it('does not promise a consent screen while the Nexo tools skip it', function () {
    /** @var OauthClient $tool */
    $tool = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'A Nexo tool', redirectUris: ['https://tool.example.test/cb'], confidential: false, user: null,
    );
    expect($tool->skipsAuthorization(User::factory()->create(), []))->toBeTrue('First-party tools no longer skip the consent screen: the help can promise it again.');

    $promises = ['es' => '/pantalla de consentimiento/iu', 'en' => '/consent screen/iu', 'pt' => '/tela de consentimento/iu'];
    foreach ($promises as $locale => $pattern) {
        expect(preg_match($pattern, helpAnswers($locale)))->toBe(0, "lang/{$locale}/help.php still promises a consent screen the Nexo tools never show.");
    }
});

it('explains connected tools and account deletion with the labels the profile shows', function () {
    foreach (['es', 'en', 'pt'] as $locale) {
        $answers = helpAnswers($locale);

        foreach (['Connected tools', 'Delete your account', 'Active sessions', 'Sign out this session'] as $label) {
            expect(str_contains($answers, __($label, [], $locale)))
                ->toBeTrue("lang/{$locale}/help.php does not name «".__($label, [], $locale).'» as the profile does.');
        }
    }
});

it('shows the new answers on /help', function () {
    $this->get(route('help', ['lang' => 'es']))->assertOk()
        ->assertSee('¿Cómo borro mi cuenta?')
        ->assertSee('¿Dónde veo las herramientas que usan mi cuenta?');
});
