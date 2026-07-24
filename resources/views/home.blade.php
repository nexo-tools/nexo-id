@extends('layouts.app')

@section('head-meta')
    <meta name="description" content="{{ __('One account for every Nexo tool — open-source, self-hostable single sign-on for the Nexo ecosystem.') }}">
    <link rel="canonical" href="{{ url('/') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ config('app.name') }} — {{ __('One account for every Nexo tool.') }}">
    <meta property="og:description" content="{{ __('One account for every Nexo tool — open-source, self-hostable single sign-on for the Nexo ecosystem.') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
@endsection

@section('content')
    <div class="flex flex-1 flex-col justify-center gap-6">
        <div class="flex items-center gap-3">
            <span class="scale-150">@include('partials.brand')</span>
        </div>
        <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
        <p class="max-w-prose text-lg text-muted">{{ __('One account for every Nexo tool.') }}</p>

        @auth
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ url('/profile') }}"
                   class="inline-flex rounded-lg bg-primary px-4 py-2 font-medium text-primary-fg hover:bg-primary-hover">{{ __('Go to your account') }}</a>
                <a href="{{ route('help') }}" class="text-sm font-medium text-primary hover:text-primary-hover">{{ __('nexo.help.title') }}</a>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ url('/login') }}"
                   class="inline-flex rounded-lg bg-primary px-4 py-2 font-medium text-primary-fg hover:bg-primary-hover">{{ __('Sign in') }}</a>
                <a href="{{ url('/register') }}"
                   class="inline-flex rounded-lg border border-line px-4 py-2 font-medium text-ink hover:bg-bg-subtle">{{ __('Create account') }}</a>
                <a href="{{ route('help') }}" class="text-sm font-medium text-primary hover:text-primary-hover">{{ __('nexo.help.title') }}</a>
            </div>
        @endauth
    </div>
@endsection
