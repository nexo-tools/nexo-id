<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
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
                <a href="{{ url('/login') }}" class="nexo-btn nexo-btn--ghost nexo-header__auth">{{ __('Sign in') }}</a>
            @endauth
        </x-slot:actions>
    </x-nexo-header>

    <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 py-12">
        @yield('content')
    </main>

    <x-nexo-footer />
</body>
</html>
