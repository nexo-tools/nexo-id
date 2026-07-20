<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-neutral-950 text-neutral-100 antialiased">
    <header class="border-b border-white/10">
        <div class="mx-auto flex w-full max-w-3xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold">
                @include('partials.brand')
                <span>{{ config('app.name') }}</span>
            </a>
            <nav class="flex items-center gap-3 text-sm text-neutral-400" aria-label="{{ __('Language') }}">
                @foreach (config('nexo.locales') as $locale)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
                       class="uppercase hover:text-neutral-100 {{ app()->getLocale() === $locale ? 'text-neutral-100 font-semibold' : '' }}"
                       @if (app()->getLocale() === $locale) aria-current="true" @endif>{{ $locale }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 py-12">
        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>
