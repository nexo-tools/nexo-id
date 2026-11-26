<?php

use App\Models\User;

it('AC-I18N-1: defaults to english', function () {
    $this->get('/')->assertSee('One account for the whole Nexo ecosystem.');
});

it('AC-I18N-1: honours the accept-language header', function () {
    $this->get('/', ['Accept-Language' => 'es-ES,es;q=0.9'])
        ->assertSee('Una sola cuenta para todo el ecosistema Nexo.');

    $this->get('/', ['Accept-Language' => 'pt-BR,pt;q=0.9'])
        ->assertSee('Uma só conta para todo o ecossistema Nexo.');
});

it('AC-I18N-1: switches with the lang parameter and persists in the shared nexo-lang cookie', function () {
    $this->get('/?lang=es')
        ->assertSee('Una sola cuenta para todo el ecosistema Nexo.')
        ->assertPlainCookie('nexo-lang', 'es');

    // A later request carrying the cookie keeps Spanish (shared across tools).
    $this->withUnencryptedCookie('nexo-lang', 'es')
        ->get('/')->assertSee('Una sola cuenta para todo el ecosistema Nexo.');
});

it('AC-I18N-1: ignores unsupported locales', function () {
    $this->get('/?lang=fr')->assertSee('One account for the whole Nexo ecosystem.');
});

it('AC-I18N-1: exposes the locale switcher', function () {
    $this->get('/')
        ->assertSee('lang=en', false)
        ->assertSee('lang=es', false)
        ->assertSee('lang=pt', false);
});

it('H-nexoid-12: the framework messages speak the page language (es/pt), not English', function () {
    User::factory()->create(['email' => 'someone@example.test']);

    foreach (['es' => 'El correo o la contraseña no coinciden.', 'pt' => 'O e-mail ou a senha não conferem.'] as $locale => $message) {
        $this->withUnencryptedCookie('nexo-lang', $locale)->from('/login')
            ->post('/login', ['email' => 'someone@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => $message]);
    }

    app()->setLocale('es');
    expect(__('validation.current_password'))->toBe('La contraseña no es correcta.')
        ->and(__('validation.required', ['attribute' => __('validation.attributes.display_name')]))->toBe('Completa el nombre visible.')
        ->and(__('passwords.sent'))->not->toContain('emailed');
});
