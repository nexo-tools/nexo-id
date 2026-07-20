@extends('layouts.auth')

@section('title', __('Verify your email'))
@section('heading', __('Verify your email'))

@section('content')
    <p class="text-sm text-neutral-400">
        {{ __('We sent a verification link to your email address. Click it to activate your account.') }}
    </p>

    @if (session('status') === 'verification-link-sent')
        <p class="mt-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300">
            {{ __('A new verification link has been sent to your email.') }}
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button type="submit"
                class="w-full rounded-lg border border-white/15 px-4 py-2 font-medium text-neutral-100 hover:bg-white/5">
            {{ __('Resend verification email') }}
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="w-full text-sm text-neutral-500 hover:text-neutral-300">
            {{ __('Sign out') }}
        </button>
    </form>
@endsection
