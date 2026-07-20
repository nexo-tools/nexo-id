@extends('layouts.auth')

@section('title', __('Sign in'))
@section('heading', __('Sign in to your account'))

@section('content')
    @if (session('status'))
        <p class="mb-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ url('/login') }}">
        @csrf

        <x-field name="email" type="email" :label="__('Email')" autocomplete="email" />
        <x-field name="password" type="password" :label="__('Password')" autocomplete="current-password" />

        <label class="mb-4 flex items-center gap-2 text-sm text-neutral-400">
            <input type="checkbox" name="remember" class="rounded border-white/20 bg-neutral-950">
            {{ __('Remember me') }}
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-emerald-500 px-4 py-2 font-medium text-emerald-950 hover:bg-emerald-400">
            {{ __('Sign in') }}
        </button>
    </form>

    <div class="mt-6 flex flex-col gap-2 text-center text-sm text-neutral-400">
        <a href="{{ url('/forgot-password') }}" class="text-emerald-400 hover:text-emerald-300">{{ __('Forgot your password?') }}</a>
        <p>
            {{ __('Don\'t have an account?') }}
            <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300">{{ __('Create account') }}</a>
        </p>
    </div>
@endsection
