{{-- One row of «Sesiones activas» (profile/show). --}}
<li class="flex flex-col gap-3 rounded-lg border border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between" data-session @if ($session->isCurrent) data-session-current @endif>
    <div class="min-w-0 text-sm">
        <p class="flex flex-wrap items-center gap-2 font-medium text-ink">
            <span data-session-device>{{ $session->deviceLabel() }}</span>
            @if ($session->isCurrent)
                <span class="rounded bg-success-subtle px-2 py-0.5 text-xs font-normal text-success-subtle-fg">{{ __('This device') }}</span>
            @endif
        </p>
        <p class="text-muted">{{ $session->isCurrent ? __('Active now') : __('Last active :time', ['time' => $session->lastActive->diffForHumans()]) }}</p>
        @if ($session->tools !== [])
            <p class="text-muted" data-session-tools>{{ __('Used in :tools', ['tools' => implode(', ', $session->tools)]) }}</p>
        @endif
        <p class="text-xs text-muted">{{ $session->ipAddress ? __('IP :ip', ['ip' => $session->ipAddress]) : __('Unknown IP') }}</p>
    </div>
    @unless ($session->isCurrent)
        <form method="POST" action="{{ route('sessions.destroy', $session->id) }}" class="shrink-0">
            @csrf
            @method('DELETE')
            <button type="submit" class="nexo-btn nexo-btn--ghost nexo-btn--sm min-h-11 w-full sm:w-auto" data-session-close>{{ __('Sign out this session') }}</button>
        </form>
    @endunless
</li>
