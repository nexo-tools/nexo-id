@extends('layouts.auth')

{{-- «¿Cerrar sesión en todo Nexo?» (ADR-010). Reached from end_session without
     a valid id_token_hint — a bare link must not be able to sign someone out of
     every Nexo tool (S9) — and from «Cerrar sesión» with an expired page (a 419
     on POST /logout). Only the CSRF-protected POST below acts.

     $return carries a tool's client_id + post_logout_redirect_uri (+ state)
     when that pair is registered, so confirming goes back to the tool; the
     controller checks it again on the POST. --}}
@section('title', __('Sign out of all of Nexo?'))
@section('heading', __('Sign out of all of Nexo?'))

@section('content')
    <p class="mb-6 text-sm text-muted">{{ __('This ends your Nexo ID session on this device and signs you out of every Nexo tool you opened with it.') }}</p>

    <form method="POST" action="{{ route('openid.end_session.confirm') }}">
        @csrf
        @foreach (($return ?? null) ?? [] as $field => $value)
            @if ($value !== '')
                <input type="hidden" name="{{ $field }}" value="{{ $value }}">
            @endif
        @endforeach
        <button type="submit" class="nexo-btn nexo-btn--primary w-full">
            {{ __('Sign out') }}
        </button>
    </form>

    <div class="mt-6 text-center text-sm">
        <a href="{{ route('profile.show') }}" class="inline-flex min-h-11 items-center text-link hover:text-link-hover">{{ __('Keep me signed in') }}</a>
    </div>
@endsection
