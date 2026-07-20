<footer class="border-t border-white/10">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-1 px-6 py-6 text-sm text-neutral-500">
        <p>{{ config('app.name') }} — {{ __('One account for every Nexo tool.') }}</p>
        <p>
            @if (config('nexo.attribution_url'))
                {{-- Instance-configured attribution (e.g. Alvaro's hosted instance, UTM-tagged). --}}
                <a href="{{ config('nexo.attribution_url') }}" rel="noopener"
                   class="hover:text-neutral-300">{{ config('nexo.attribution_text') ?: config('nexo.attribution_url') }}</a>
            @else
                {{-- Neutral open-source default: no external promotion, links to the repo. --}}
                <a href="https://github.com/alvarocdev-git/nexo-id" rel="noopener"
                   class="hover:text-neutral-300">{{ __('Made with :name', ['name' => config('app.name')]) }}</a>
            @endif
        </p>
    </div>
</footer>
