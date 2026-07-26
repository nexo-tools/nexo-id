<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="robots" content="noindex">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Stamp <html data-theme> before the stylesheet loads (no FOUC). --}}
    @include('partials.theme-init')
    @include('partials.beacon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-bg px-6 py-12 text-ink antialiased">
    {{-- Personal-preference switchers (locale + theme). The ecosystem app-switcher
         is intentionally omitted here so the sign-in / consent flow stays focused. --}}
    <div class="mx-auto flex w-full max-w-sm items-center justify-end gap-1">
        <a href="{{ route('help') }}" class="nexo-btn nexo-btn--ghost text-sm">{{ __('nexo.help.title') }}</a>
        <x-nexo-locale-switcher />
        <x-nexo-theme-toggle />
    </div>

    <main class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center">
        <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center gap-2 text-lg font-semibold">
            @include('partials.brand')
            <span>{{ config('app.name') }}</span>
        </a>

        <div class="rounded-2xl border border-line bg-surface p-6">
            <h1 class="mb-6 text-xl font-semibold">@yield('heading')</h1>
            @yield('content')
        </div>
    </main>
</body>
</html>
