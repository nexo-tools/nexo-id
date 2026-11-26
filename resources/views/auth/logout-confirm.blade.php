@extends('layouts.auth')

{{-- end_session reached without a valid id_token_hint (ADR-010): a bare link
     must not be able to sign someone out of every Nexo tool (S9), so it asks
     first and only the CSRF-protected POST below acts. --}}
@section('title', __('Sign out of all of Nexo?'))
@section('heading', __('Sign out of all of Nexo?'))

@section('content')
    <p class="mb-6 text-sm text-muted">{{ __('This ends your Nexo ID session on this device and signs you out of every Nexo tool you opened with it.') }}</p>

    <form method="POST" action="{{ route('openid.end_session.confirm') }}">
        @csrf
        <button type="submit" class="nexo-btn nexo-btn--primary w-full">
            {{ __('Sign out') }}
        </button>
    </form>

    <div class="mt-6 text-center text-sm">
        <a href="{{ route('profile.show') }}" class="text-primary hover:text-primary-hover">{{ __('Keep me signed in') }}</a>
    </div>
@endsection
