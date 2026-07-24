@extends('layouts.auth')

@section('title', __('Reset password'))
@section('heading', __('Reset your password'))

@section('content')
    @if (session('status'))
        <p class="mb-4 rounded-lg bg-success-subtle px-3 py-2 text-sm text-success-subtle-fg">{{ session('status') }}</p>
    @endif

    <p class="mb-4 text-sm text-muted">
        {{ __('Enter your email and we will send you a link to reset your password.') }}
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-field name="email" type="email" :label="__('Email')" autocomplete="email" />

        <button type="submit"
                class="w-full rounded-lg bg-primary px-4 py-2 font-medium text-primary-fg hover:bg-primary-hover">
            {{ __('Send reset link') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        <a href="{{ route('login') }}" class="text-primary hover:text-primary-hover">{{ __('Back to sign in') }}</a>
    </p>
@endsection
