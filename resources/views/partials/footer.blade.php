<footer class="border-t border-white/10">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-1 px-6 py-6 text-sm text-neutral-500">
        <p>{{ config('app.name') }} — {{ __('One account for every Nexo tool.') }}</p>
        <p>
            {{-- Instance-configurable attribution: neutral default → repo; Alvaro's
                 instance overrides label/url via NEXO_ATTRIBUTION_* in .env. --}}
            <a href="{{ config('nexo.attribution.url') }}" rel="noopener"
               class="hover:text-neutral-300">{{ config('nexo.attribution.label') }}</a>
        </p>
    </div>
</footer>
