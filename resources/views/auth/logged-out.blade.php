@extends('layouts.auth')

{{-- «Cerraste sesión en todo Nexo»: where every sign-out of Nexo ID itself
     lands (POST /logout, the confirmed end_session, «en todos los
     dispositivos»), as a GET page (route signed-out) so a reload never posts
     again. With a session it is not shown (the route sends to the account). --}}
@php
    $everywhere = ($reason ?? null) === 'everywhere';
    $heading = $everywhere ? __('You signed out on every device') : __('You signed out of all of Nexo');
@endphp

@section('title', $heading)
@section('heading', $heading)

@section('content')
    <p class="mb-6 text-sm text-muted" data-nexo-signed-out>
        {{ $everywhere
            ? __('Your Nexo ID session ended on all your devices, and so did the Nexo tools you opened with it.')
            : __('Your Nexo ID session ended on this device, and so did the Nexo tools you opened with it.') }}
    </p>

    <a href="{{ route('login') }}" class="nexo-btn nexo-btn--primary w-full" data-nexo-sign-in-again>
        {{ __('Sign in again') }}
    </a>

    <p class="mt-6 text-center text-sm">
        <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center text-link hover:text-link-hover">{{ __('Back to home') }}</a>
    </p>
@endsection
