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
                class="w-full rounded-lg bg-emerald-500 px-4 py-2 font-medium text-emerald-950 hover:bg-emerald-400">
            {{ __('Create account') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-400">
        {{ __('Already have an account?') }}
        <a href="{{ url('/login') }}" class="text-emerald-400 hover:text-emerald-300">{{ __('Sign in') }}</a>
    </p>
@endsection
