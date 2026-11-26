@extends('layouts.auth')

{{-- Sign-in, chrome v2: the canonical <x-nexo-sign-in-options> (STANDARD
     «Login», LoginNexoIdFirstTest). Nexo ID is the identity provider, not a
     client, so there is no «Continuar con Nexo ID» here: the component renders
     the password form alone and closes the card with «¿No tienes una cuenta?
     Crear cuenta». «Recordarme» is Nexo ID's own (30 days, ADR-010) and
     NexoChrome::allowsRemember() is true with no SSO client config. --}}
@section('title', __('Sign in'))
@section('heading', __('Sign in to your account'))

@section('content')
    @if (session('status'))
        <p class="nexo-flash mb-4" role="status">{{ session('status') }}</p>
    @endif

    <x-nexo-sign-in-options>
        <form method="POST" action="{{ url('/login') }}">
            @csrf

            <x-field name="email" type="email" :label="__('Email')" autocomplete="email" />
            <x-field name="password" type="password" :label="__('Password')" autocomplete="current-password" />

            @if (\App\Support\NexoChrome::allowsRemember())
                <label class="mb-4 flex min-h-11 items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="remember" class="rounded border-control bg-bg accent-primary">
                    {{ __('Remember me') }}
                </label>
            @endif

            <button type="submit"
                    class="nexo-btn nexo-btn--primary w-full">
                {{ __('Sign in') }}
            </button>

            <p class="mt-4 text-center text-sm">
                <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-primary hover:text-primary-hover">{{ __('Forgot your password?') }}</a>
            </p>
        </form>
    </x-nexo-sign-in-options>
@endsection
