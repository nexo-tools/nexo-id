@extends('layouts.app')

{{-- «Tu cuenta», the panel of Nexo ID. «Cerrar sesión» (this device) lives in
     the header's account menu and the phone sheet only: the controls on this
     page are named for what they close — «Cerrar esta sesión», «Cerrar las
     demás sesiones», «Cerrar sesión en todos los dispositivos» — so none of them
     reads like the one in the menu (H-nexoid-06). Confirmations go through
     data-confirm (nexo-ui.js): the CSP drops inline handlers. --}}
@section('title', __('Your account'))

@section('content')
    <div class="flex flex-col gap-12">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('Your account') }}</h1>
            <p class="mt-1 break-all text-sm text-muted">{{ $user->email }}</p>
        </div>

        @if (session('status'))
            <p class="nexo-flash" role="status" data-profile-status>{{ session('status') }}</p>
        @endif

        {{-- Profile information (AC-PROFILE-1) --}}
        <section aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="mb-4 text-lg font-medium">{{ __('Profile') }}</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="max-w-sm">
                @csrf
                @method('PATCH')
                <x-field name="display_name" :label="__('Display name')" :value="$user->display_name" autocomplete="name" />
                <div class="mb-4">
                    {{-- Not an input: a disabled field looked editable in dark mode
                         (H-nexoid-12). It is information, so it reads as text. --}}
                    <p id="profile-email-label" class="mb-1 block text-sm text-muted">{{ __('Email') }}</p>
                    <p aria-labelledby="profile-email-label" class="break-all rounded-lg border border-dashed border-line px-3 py-2 text-muted">{{ $user->email }}</p>
                    <p class="mt-1 text-xs text-muted">{{ __('Your email cannot be changed yet.') }}</p>
                </div>
                <button type="submit" class="nexo-btn nexo-btn--primary">{{ __('Save') }}</button>
            </form>
        </section>

        {{-- Change password (AC-PROFILE-2) --}}
        <section aria-labelledby="password-heading">
            <h2 id="password-heading" class="mb-4 text-lg font-medium">{{ __('Change password') }}</h2>
            <form method="POST" action="{{ route('password.update') }}" class="max-w-sm">
                @csrf
                @method('PUT')
                <x-field name="current_password" type="password" :label="__('Current password')" autocomplete="current-password" />
                <x-field name="password" type="password" :label="__('New password')" autocomplete="new-password" />
                <x-field name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />
                <button type="submit" class="nexo-btn nexo-btn--primary">{{ __('Update password') }}</button>
            </form>
        </section>

        {{-- Connected tools (H-nexoid-03): where this account signed in, when it
             last did, and «Abrir» (the tool's url + entry: its panel, signed in). --}}
        <section aria-labelledby="tools-heading" data-connected-tools>
            <h2 id="tools-heading" class="text-lg font-medium">{{ __('Connected tools') }}</h2>
            <p class="mb-4 mt-1 text-sm text-muted">{{ __('The Nexo tools where you used this account.') }}</p>

            @if ($connectedTools->isEmpty())
                <p class="text-sm text-muted" data-connected-tools-empty>{{ __('You have not used this account in any tool yet.') }}</p>
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($connectedTools as $tool)
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-line px-4 py-3" data-connected-tool>
                            @if ($tool->mark)
                                <img src="{{ $tool->mark }}" alt="" width="32" height="32" class="size-8 shrink-0">
                            @endif
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-medium text-ink">{{ $tool->name }}</p>
                                <p class="text-muted">
                                    @if ($tool->lastUsedAt)
                                        {{ __('Last used :time', ['time' => $tool->lastUsedAt->diffForHumans()]) }}
                                    @else
                                        {{ __('Used before') }}
                                    @endif
                                </p>
                            </div>
                            @if ($tool->openUrl)
                                <a href="{{ $tool->openUrl }}" class="nexo-btn nexo-btn--ghost nexo-btn--sm min-h-11" data-connected-tool-open>{{ __('Open') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Active sessions (AC-SESS-1/2/3, H-nexoid-06): readable rows —
             browser and system, last activity, «Este dispositivo», the tools
             each one signed in to. --}}
        <section aria-labelledby="sessions-heading" data-sessions>
            <h2 id="sessions-heading" class="text-lg font-medium">{{ __('Active sessions') }}</h2>
            <p class="mb-4 mt-1 text-sm text-muted">{{ __('The devices where your account is signed in. Closing a session also signs that device out of the Nexo tools it opened.') }}</p>

            {{-- The current device first; past five rows the rest fold away, so a
                 long list (one row per browser that ever signed in) does not
                 bury the account controls under it. --}}
            @if ($sessions->isEmpty())
                <p class="text-sm text-muted">{{ __('No other active sessions.') }}</p>
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($sessions->take(5) as $session)
                        @include('profile.session-row', ['session' => $session])
                    @endforeach
                </ul>
                @if ($sessions->count() > 5)
                    <details class="mt-3" data-sessions-more>
                        <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-link hover:text-link-hover">{{ trans_choice('{1} Show 1 more session|[2,*] Show :count more sessions', $sessions->count() - 5, ['count' => $sessions->count() - 5]) }}</summary>
                        <ul class="mt-3 flex flex-col gap-3">
                            @foreach ($sessions->slice(5) as $session)
                                @include('profile.session-row', ['session' => $session])
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif

            {{-- D3 (ADR-010): the menu's «Cerrar sesión» closes this device;
                 these close the others, or every device including this one. --}}
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                @if ($sessions->count() > 1)
                    <form method="POST" action="{{ route('sessions.destroy-others') }}"
                          data-confirm="{{ __('Sign out of every other session? Any device still signed in will have to sign in again.') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="nexo-btn nexo-btn--ghost min-h-11 w-full sm:w-auto" data-sessions-close-others>{{ __('Sign out all other sessions') }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('logout.everywhere') }}"
                      data-confirm="{{ __('Sign out on every device, this one included? Every device will have to sign in again.') }}">
                    @csrf
                    <button type="submit" class="nexo-btn nexo-btn--ghost min-h-11 w-full sm:w-auto" data-sessions-close-all>{{ __('Sign out of all devices') }}</button>
                </form>
            </div>
        </section>

        {{-- Delete the account (H-nexoid-10). Honest about what it does NOT do:
             what the person keeps inside each tool stays there. --}}
        <section aria-labelledby="delete-heading" class="rounded-xl border border-line p-5" data-delete-account>
            <h2 id="delete-heading" class="text-lg font-medium">{{ __('Delete your account') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ __('This deletes your Nexo ID account: your name, your email, your password and your sessions. Every Nexo tool where you are signed in signs you out.') }}</p>
            <p class="mt-2 text-sm text-ink" data-delete-account-warning>{{ __('What you have in each tool is not deleted with this, and afterwards you will not be able to get into it with this account. If you want to delete it, do it first in each tool.') }}</p>

            @if ($connectedTools->isNotEmpty())
                <ul class="mt-3 flex flex-col gap-1 text-sm" data-delete-account-tools>
                    @foreach ($connectedTools as $tool)
                        <li>
                            @if ($tool->openUrl)
                                <a href="{{ $tool->openUrl }}" class="inline-flex min-h-11 items-center text-primary hover:text-primary-hover hover:underline">{{ $tool->name }}</a>
                            @else
                                <span class="inline-flex min-h-11 items-center text-ink">{{ $tool->name }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('profile.destroy') }}" class="mt-4 max-w-sm"
                  data-confirm="{{ __('Delete your Nexo ID account? This cannot be undone.') }}">
                @csrf
                @method('DELETE')
                <div class="mb-4">
                    <label for="delete_password" class="mb-1 block text-sm text-muted">{{ __('Your password, to confirm') }}</label>
                    <input type="password" name="delete_password" id="delete_password" required autocomplete="current-password"
                           @error('delete_password', 'deleteAccount') aria-invalid="true" aria-describedby="delete_password-error" @enderror
                           class="min-h-11 w-full rounded-lg border border-control bg-bg px-3 py-2 text-ink focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                    @error('delete_password', 'deleteAccount')
                        <p id="delete_password-error" class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="nexo-btn nexo-btn--danger" data-delete-account-submit>{{ __('Delete my account') }}</button>
            </form>
        </section>
    </div>
@endsection
