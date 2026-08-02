@extends('layouts.app')

@section('title', __('Your account'))

@section('content')
    <div class="flex flex-col gap-10">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ __('Your account') }}</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-muted hover:text-ink">{{ __('Sign out') }}</button>
            </form>
        </div>

        @if (session('status'))
            <p class="nexo-flash" role="status">{{ session('status') }}</p>
        @endif

        {{-- Profile information (AC-PROFILE-1) --}}
        <section>
            <h2 class="mb-4 text-lg font-medium">{{ __('Profile') }}</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="max-w-sm">
                @csrf
                @method('PATCH')
                <x-field name="display_name" :label="__('Display name')" :value="$user->display_name" autocomplete="name" />
                <div class="mb-4">
                    <label for="profile-email" class="mb-1 block text-sm text-muted">{{ __('Email') }}</label>
                    <input type="email" id="profile-email" value="{{ $user->email }}" disabled
                           class="w-full rounded-lg border border-control bg-surface-sunken px-3 py-2 text-muted">
                </div>
                <button type="submit"
                        class="nexo-btn nexo-btn--primary">{{ __('Save') }}</button>
            </form>
        </section>

        {{-- Change password (AC-PROFILE-2) --}}
        <section>
            <h2 class="mb-4 text-lg font-medium">{{ __('Change password') }}</h2>
            <form method="POST" action="{{ route('password.update') }}" class="max-w-sm">
                @csrf
                @method('PUT')
                <x-field name="current_password" type="password" :label="__('Current password')" autocomplete="current-password" />
                <x-field name="password" type="password" :label="__('New password')" autocomplete="new-password" />
                <x-field name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />
                <button type="submit"
                        class="nexo-btn nexo-btn--primary">{{ __('Update password') }}</button>
            </form>
        </section>

        {{-- Active sessions (AC-SESS-1/2/3) --}}
        <section>
            {{-- flex-wrap + gap: at 390px the title and the sign-out-others link
                 overlapped glyph on glyph; wrapping puts the link on its own line
                 instead of on top of the heading. --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <h2 class="text-lg font-medium">{{ __('Active sessions') }}</h2>
                @if ($sessions->count() > 1)
                    {{-- Alpine, not an inline onsubmit: the CSP has no 'unsafe-hashes',
                         so an on* attribute would be dropped and the guard would
                         silently never run. --}}
                    <form method="POST" action="{{ route('sessions.destroy-others') }}"
                          x-data @submit="if (! confirm(@js(__('Sign out of every other session? Any device still signed in will have to sign in again.')))) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-danger hover:underline">{{ __('Sign out all other sessions') }}</button>
                    </form>
                @endif
            </div>

            <ul class="flex flex-col gap-3">
                @forelse ($sessions as $session)
                    <li class="flex items-center justify-between rounded-lg border border-line px-4 py-3">
                        <div class="text-sm">
                            <p class="text-ink">
                                {{ $session->ipAddress ?? __('Unknown IP') }}
                                @if ($session->isCurrent)
                                    <span class="ml-2 rounded bg-success-subtle px-2 py-0.5 text-xs text-success-subtle-fg">{{ __('This device') }}</span>
                                @endif
                            </p>
                            <p class="text-muted">{{ \Illuminate\Support\Str::limit($session->userAgent ?? __('Unknown device'), 60) }}</p>
                            <p class="text-muted">{{ __('Last active :time', ['time' => $session->lastActive->diffForHumans()]) }}</p>
                        </div>
                        @unless ($session->isCurrent)
                            <form method="POST" action="{{ route('sessions.destroy', $session->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-danger hover:underline">{{ __('Sign out') }}</button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="text-sm text-muted">{{ __('No other active sessions.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
