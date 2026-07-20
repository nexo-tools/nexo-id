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
<body class="flex min-h-full flex-col items-center justify-center bg-neutral-950 px-6 py-12 text-neutral-100 antialiased">
    <main class="w-full max-w-sm">
        <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center gap-2 text-lg font-semibold">
            @include('partials.brand')
            <span>{{ config('app.name') }}</span>
        </a>

        <div class="rounded-2xl border border-white/10 bg-neutral-900/60 p-6">
            <h1 class="mb-6 text-xl font-semibold">@yield('heading')</h1>
            @yield('content')
        </div>
    </main>
</body>
</html>
