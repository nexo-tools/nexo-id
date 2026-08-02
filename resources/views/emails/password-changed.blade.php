{{-- Security notice. The account this protects is the one that opens every
     other tool in the ecosystem, so the copy points at the action that helps
     if this was not the owner. --}}
<x-nexo-mail::layout
    :title="__('Your :app password was changed', ['app' => config('app.name')])"
    :preheader="__('The password for your account was just changed.')">

    <h1 class="nexo-ink" style="margin:0 0 16px; font-size:20px; line-height:1.3; font-weight:700; color:#18181b;">
        {{ __('Your :app password was changed', ['app' => config('app.name')]) }}
    </h1>

    <p style="margin:0 0 20px; font-size:15px; line-height:1.6;">
        {{ __('The password for your account was just changed.') }}
    </p>

    <p style="margin:0 0 4px; font-size:15px; line-height:1.6;">
        {{ __('If this was not you, reset your password immediately and review your active sessions.') }}
    </p>
    <x-nexo-mail::code>{{ route('password.request') }}</x-nexo-mail::code>
</x-nexo-mail::layout>
