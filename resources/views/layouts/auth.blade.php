<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    {{-- noindex is unconditional here, not a head-meta section: every screen this
         shell renders is part of the sign-in or consent flow. --}}
    <meta name="robots" content="noindex">
    @include('partials.head')
</head>
<body class="flex min-h-full flex-col bg-bg text-ink antialiased">
    {{-- Full family chrome. This layout used to be the ecosystem's documented
         "focused auth" exception (no header, just a loose row of locale + theme
         controls), but crossing from any tool's sign-in to the IdP's made the
         family look broken, so Alvaro removed the exception on 2026-08-02:
         six tools, one chrome. The brand now lives in the header's wordmark —
         no centered wordmark above the card. --}}
    <x-nexo-header brand="Nexo ID" mark="/ecosystem/nexoid.svg" />

    <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 py-12">
        <x-nexo-auth-card>
            <h1 class="mb-6 text-xl font-semibold">@yield('heading')</h1>
            @yield('content')
        </x-nexo-auth-card>
    </main>

    {{-- The footer stays: the consent screen is exactly where someone decides
         whether to hand an account over, so privacy and terms must be one click
         away. --}}
    <x-nexo-footer />
</body>
</html>
