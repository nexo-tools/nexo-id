@extends('layouts.app')

@section('head-meta')
    <meta name="description" content="{{ __('One account for every Nexo tool — open-source, self-hostable single sign-on for the Nexo ecosystem.') }}">
    <link rel="canonical" href="{{ url('/') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ config('app.name') }} — {{ __('One account for every Nexo tool.') }}">
    <meta property="og:description" content="{{ __('One account for every Nexo tool — open-source, self-hostable single sign-on for the Nexo ecosystem.') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary">
@endsection

@section('content')
    <div class="flex flex-1 flex-col justify-center gap-6">
        <div class="flex items-center gap-3">
            <span class="scale-150">@include('partials.brand')</span>
        </div>
        <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
        <p class="max-w-prose text-lg text-neutral-400">{{ __('One account for every Nexo tool.') }}</p>

        @auth
            <div>
                <a href="{{ url('/profile') }}"
                   class="inline-flex rounded-lg bg-emerald-500 px-4 py-2 font-medium text-emerald-950 hover:bg-emerald-400">{{ __('Go to your account') }}</a>
            </div>
        @else
            <div class="flex gap-3">
                <a href="{{ url('/login') }}"
                   class="inline-flex rounded-lg bg-emerald-500 px-4 py-2 font-medium text-emerald-950 hover:bg-emerald-400">{{ __('Sign in') }}</a>
                <a href="{{ url('/register') }}"
                   class="inline-flex rounded-lg border border-white/15 px-4 py-2 font-medium text-neutral-100 hover:bg-white/5">{{ __('Create account') }}</a>
            </div>
        @endauth
    </div>
@endsection
