<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>@yield('title', config('app.name'))</title>
    @yield('head-meta')
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    {{-- Stamp <html data-theme> before the stylesheet loads (no FOUC). --}}
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-bg font-sans text-ink antialiased">
    <x-nexo-header brand="Nexo ID" mark="/ecosystem/nexoid.svg" :home="url('/')">
        <x-slot:nav>
            <a href="{{ route('help') }}"
               class="rounded-md px-2 py-1 text-sm font-medium hover:bg-bg-subtle {{ request()->routeIs('help') ? 'text-ink' : 'text-muted' }}"
               @if (request()->routeIs('help')) aria-current="page" @endif>{{ __('nexo.help.title') }}</a>
        </x-slot:nav>
        <x-slot:actions>
            @auth
                <a href="{{ url('/profile') }}" class="nexo-btn nexo-btn--ghost">{{ __('Your account') }}</a>
            @else
                <a href="{{ url('/login') }}" class="nexo-btn nexo-btn--ghost">{{ __('Sign in') }}</a>
            @endauth
        </x-slot:actions>
    </x-nexo-header>

    <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 py-12">
        @yield('content')
    </main>

    <x-nexo-footer />
</body>
</html>
