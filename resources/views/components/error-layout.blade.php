{{-- Shared shell for every error page, so a 404 or an expired session still looks
     like Nexo ID. Laravel resolves errors/<code>.blade.php on its own (no parent
     layout, no section stack), hence a component that includes partials.head
     directly instead of @extends('layouts.app'). --}}
@props(['code', 'title', 'message'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-full flex-col bg-bg font-sans text-ink antialiased">
    <x-nexo-header brand="Nexo ID" mark="/ecosystem/nexoid.svg" :home="url('/')" />

    <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center gap-3 px-6 py-16 text-center">
        <p class="text-6xl font-bold tabular-nums text-primary">{{ $code }}</p>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="max-w-prose text-muted">{{ $message }}</p>

        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ url('/') }}"
               class="nexo-btn nexo-btn--primary">{{ __('Back to home') }}</a>
            <a href="{{ route('help') }}" class="text-sm font-medium text-primary hover:text-primary-hover">{{ __('nexo.help.title') }}</a>
        </div>
    </main>

    <x-nexo-footer />
</body>
</html>
