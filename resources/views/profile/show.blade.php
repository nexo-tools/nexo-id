@extends('layouts.app')

@section('title', __('Your account'))

@section('content')
    <div class="flex flex-col gap-10">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ __('Your account') }}</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-neutral-400 hover:text-neutral-100">{{ __('Sign out') }}</button>
            </form>
        </div>

        @if (session('status'))
            <p class="rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
        @endif

        {{-- Profile information (AC-PROFILE-1) --}}
        <section>
            <h2 class="mb-4 text-lg font-medium">{{ __('Profile') }}</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="max-w-sm">
                @csrf
                @method('PATCH')
                <x-field name="display_name" :label="__('Display name')" :value="$user->display_name" autocomplete="name" />
                <div class="mb-4">
                    <label class="mb-1 block text-sm text-neutral-300">{{ __('Email') }}</label>
                    <input type="email" value="{{ $user->email }}" disabled
                           class="w-full rounded-lg border border-white/10 bg-neutral-900 px-3 py-2 text-neutral-500">
                </div>
                <button type="submit"
                        class="rounded-lg bg-emerald-500 px-4 py-2 font-medium text-emerald-950 hover:bg-emerald-400">{{ __('Save') }}</button>
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
                        class="rounded-lg bg-emerald-500 px-4 py-2 font-medium text-emerald-950 hover:bg-emerald-400">{{ __('Update password') }}</button>
            </form>
        </section>

        {{-- Active sessions (AC-SESS-1/2/3) --}}
        <section>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-medium">{{ __('Active sessions') }}</h2>
                @if ($sessions->count() > 1)
                    <form method="POST" action="{{ route('sessions.destroy-others') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-400 hover:text-red-300">{{ __('Sign out all other sessions') }}</button>
                    </form>
                @endif
            </div>

            <ul class="flex flex-col gap-3">
                @forelse ($sessions as $session)
                    <li class="flex items-center justify-between rounded-lg border border-white/10 px-4 py-3">
                        <div class="text-sm">
                            <p class="text-neutral-200">
                                {{ $session->ipAddress ?? __('Unknown IP') }}
                                @if ($session->isCurrent)
                                    <span class="ml-2 rounded bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-300">{{ __('This device') }}</span>
                                @endif
                            </p>
                            <p class="text-neutral-500">{{ \Illuminate\Support\Str::limit($session->userAgent ?? __('Unknown device'), 60) }}</p>
                            <p class="text-neutral-600">{{ __('Last active :time', ['time' => $session->lastActive->diffForHumans()]) }}</p>
                        </div>
                        @unless ($session->isCurrent)
                            <form method="POST" action="{{ route('sessions.destroy', $session->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-400 hover:text-red-300">{{ __('Sign out') }}</button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="text-sm text-neutral-500">{{ __('No other active sessions.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
