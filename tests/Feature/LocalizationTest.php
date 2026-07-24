<?php

it('AC-I18N-1: defaults to english', function () {
    $this->get('/')->assertSee('One account for every Nexo tool.');
});

it('AC-I18N-1: honours the accept-language header', function () {
    $this->get('/', ['Accept-Language' => 'es-ES,es;q=0.9'])
        ->assertSee('Una sola cuenta para todas las herramientas Nexo.');

    $this->get('/', ['Accept-Language' => 'pt-BR,pt;q=0.9'])
        ->assertSee('Uma conta para todas as ferramentas Nexo.');
});

it('AC-I18N-1: switches with the lang parameter and persists in the shared nexo-lang cookie', function () {
    $this->get('/?lang=es')
        ->assertSee('Una sola cuenta para todas las herramientas Nexo.')
        ->assertPlainCookie('nexo-lang', 'es');

    // A later request carrying the cookie keeps Spanish (shared across tools).
    $this->withUnencryptedCookie('nexo-lang', 'es')
        ->get('/')->assertSee('Una sola cuenta para todas las herramientas Nexo.');
});

it('AC-I18N-1: ignores unsupported locales', function () {
    $this->get('/?lang=fr')->assertSee('One account for every Nexo tool.');
});

it('AC-I18N-1: exposes the locale switcher', function () {
    $this->get('/')
        ->assertSee('lang=en', false)
        ->assertSee('lang=es', false)
        ->assertSee('lang=pt', false);
});
