@extends('layouts.auth')

@section('title', __('You are now signed out'))
@section('heading', __('You are now signed out'))

@section('content')
    <p class="mb-6 text-sm text-muted">{{ __('Your Nexo ID session has ended.') }}</p>

    <a href="{{ route('login') }}"
       class="nexo-btn nexo-btn--primary w-full">
        {{ __('Sign in') }}
    </a>
@endsection
