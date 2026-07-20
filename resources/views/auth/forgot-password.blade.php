@extends('layouts.auth')

@section('title', __('Reset password'))
@section('heading', __('Reset your password'))

@section('content')
    @if (session('status'))
        <p class="mb-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif

    <p class="mb-4 text-sm text-neutral-400">
        {{ __('Enter your email and we will send you a link to reset your password.') }}
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-field name="email" type="email" :label="__('Email')" autocomplete="email" />

        <button type="submit"
                class="w-full rounded-lg bg-emerald-500 px-4 py-2 font-medium text-emerald-950 hover:bg-emerald-400">
            {{ __('Send reset link') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-400">
        <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300">{{ __('Back to sign in') }}</a>
    </p>
@endsection
