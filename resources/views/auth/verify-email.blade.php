@extends('layouts.auth')

@section('title', __('Verify your email'))
@section('heading', __('Verify your email'))

@section('content')
    <p class="text-sm text-muted">
        {{ __('We sent a verification link to your email address. Click it to activate your account.') }}
    </p>

    @if (session('status') === 'verification-link-sent')
        <p class="mt-4 rounded-lg bg-success-subtle px-3 py-2 text-sm text-success-subtle-fg">
            {{ __('A new verification link has been sent to your email.') }}
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button type="submit"
                class="nexo-btn nexo-btn--ghost w-full">
            {{ __('Resend verification email') }}
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="w-full text-sm text-muted hover:text-ink">
            {{ __('Sign out') }}
        </button>
    </form>
@endsection
