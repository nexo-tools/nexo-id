<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-full flex-col bg-bg font-sans text-ink antialiased">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-4 focus:py-2 focus:text-link">
        {{ __('Skip to content') }}
    </a>

    {{-- Chrome v2: the same header on every layout, no props — brand, mark,
         panel («Tu cuenta») and the account menu all come from the registry and
         App\Support\NexoChrome. «Cerrar sesión» lives in the account menu and
         the phone sheet, nowhere else. --}}
    <x-nexo-header />

    <main id="contenido" class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 py-12">
        @yield('content')
    </main>

    <x-nexo-footer />
</body>
</html>
