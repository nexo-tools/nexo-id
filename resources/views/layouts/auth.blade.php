<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    {{-- noindex is unconditional here, not a head-meta section: every screen this
         shell renders is part of the sign-in or consent flow. --}}
    <meta name="robots" content="noindex">
    @include('partials.head')
</head>
<body class="flex min-h-full flex-col bg-bg px-6 py-12 text-ink antialiased">
    {{-- Personal-preference switchers (locale + theme). The ecosystem app-switcher
         is intentionally omitted here so the sign-in / consent flow stays focused:
         you are signing in TO the identity provider, and a switcher would invite
         you to leave mid-flow. Dropping the header is a layout decision; dropping
         a person's theme or language is not, so these two stay (STANDARD.md
         "Auth y errores", the documented FOCUSED_AUTH exception). --}}
    <div class="mx-auto flex w-full max-w-md items-center justify-end gap-1">
        <a href="{{ route('help') }}" class="nexo-btn nexo-btn--ghost text-sm">{{ __('nexo.help.title') }}</a>
        <x-nexo-locale-switcher />
        <x-nexo-theme-toggle />
    </div>

    <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
        <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center gap-2 text-lg font-semibold">
            @include('partials.brand')
            <span>{{ config('app.name') }}</span>
        </a>

        <x-nexo-auth-card>
            <h1 class="mb-6 text-xl font-semibold">@yield('heading')</h1>
            @yield('content')
        </x-nexo-auth-card>
    </main>

    {{-- The app-switcher is omitted above to keep the flow focused, but the footer
         stays: the consent screen is exactly where someone decides whether to hand
         an account over, so privacy and terms must be one click away. --}}
    <x-nexo-footer />
</body>
</html>
