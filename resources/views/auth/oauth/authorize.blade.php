@extends('layouts.auth')

@section('title', __('Authorize'))
@section('heading', __('Authorize :client', ['client' => $client->name]))

@section('content')
    <p class="mb-4 text-sm text-muted">
        {{ __(':client wants to access your Nexo ID account.', ['client' => $client->name]) }}
    </p>

    @if (count($scopes) > 0)
        <div class="mb-6">
            <p class="mb-2 text-sm text-muted">{{ __('This will allow it to:') }}</p>
            <ul class="list-disc space-y-1 pl-5 text-sm text-muted">
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex gap-3">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit"
                    class="w-full rounded-lg bg-primary px-4 py-2 font-medium text-primary-fg hover:bg-primary-hover">{{ __('Authorize') }}</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit"
                    class="w-full rounded-lg border border-line px-4 py-2 font-medium text-ink hover:bg-bg-subtle">{{ __('Cancel') }}</button>
        </form>
    </div>
@endsection
