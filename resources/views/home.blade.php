@extends('layouts.app')

@section('seo')
    <x-nexo-seo
        :title="config('app.name').' — '.__('One account for every Nexo tool.')"
        :description="__('One account for every Nexo tool — open-source, self-hostable single sign-on for the Nexo ecosystem.')"
        :canonical="url('/')" />
@endsection

@section('content')
    <div class="flex flex-1 flex-col items-center justify-center gap-6 text-center">
        <div class="flex items-center justify-center gap-3">
            <span class="scale-150">@include('partials.brand')</span>
        </div>
        <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
        <p class="max-w-prose text-lg text-muted">{{ __('One account for every Nexo tool.') }}</p>

        @auth
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ url('/profile') }}"
                   class="nexo-btn nexo-btn--primary">{{ __('Go to your account') }}</a>
                <a href="{{ route('help') }}" class="text-sm font-medium text-primary hover:text-primary-hover">{{ __('nexo.help.title') }}</a>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ url('/login') }}"
                   class="nexo-btn nexo-btn--primary">{{ __('Sign in') }}</a>
                <a href="{{ url('/register') }}"
                   class="nexo-btn nexo-btn--ghost">{{ __('Create account') }}</a>
                <a href="{{ route('help') }}" class="text-sm font-medium text-primary hover:text-primary-hover">{{ __('nexo.help.title') }}</a>
            </div>
        @endauth
    </div>
@endsection
