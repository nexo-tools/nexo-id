@extends('layouts.auth')

@section('title', __('Sign in'))
@section('heading', __('Sign in to your account'))

@section('content')
    @if (session('status'))
        <p class="mb-4 rounded-lg bg-success-subtle px-3 py-2 text-sm text-success-subtle-fg">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ url('/login') }}">
        @csrf

        <x-field name="email" type="email" :label="__('Email')" autocomplete="email" />
        <x-field name="password" type="password" :label="__('Password')" autocomplete="current-password" />

        <label class="mb-4 flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="remember" class="rounded border-control bg-bg accent-primary">
            {{ __('Remember me') }}
        </label>

        <button type="submit"
                class="nexo-btn nexo-btn--primary w-full">
            {{ __('Sign in') }}
        </button>
    </form>

    <div class="mt-6 flex flex-col gap-2 text-center text-sm text-muted">
        <a href="{{ url('/forgot-password') }}" class="text-primary hover:text-primary-hover">{{ __('Forgot your password?') }}</a>
        <p>
            {{ __('Don\'t have an account?') }}
            <a href="{{ route('register') }}" class="text-primary hover:text-primary-hover">{{ __('Create account') }}</a>
        </p>
    </div>
@endsection
