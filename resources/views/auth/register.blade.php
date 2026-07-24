@extends('layouts.auth')

@section('title', __('Create account'))
@section('heading', __('Create your account'))

@section('content')
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <x-field name="display_name" :label="__('Display name')" autocomplete="name" />
        <x-field name="email" type="email" :label="__('Email')" autocomplete="email" />
        <x-field name="password" type="password" :label="__('Password')" autocomplete="new-password" />
        <x-field name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />

        <button type="submit"
                class="w-full rounded-lg bg-primary px-4 py-2 font-medium text-primary-fg hover:bg-primary-hover">
            {{ __('Create account') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        {{ __('Already have an account?') }}
        <a href="{{ url('/login') }}" class="text-primary hover:text-primary-hover">{{ __('Sign in') }}</a>
    </p>
@endsection
