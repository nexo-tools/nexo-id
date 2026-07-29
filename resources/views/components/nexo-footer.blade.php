{{-- Standard ecosystem footer: "part of Nexo" -> hub, "powered by" attribution
     (canonical NEXO_ATTRIBUTION_LABEL/URL), and a source link to the GitHub org.
     i18n: nexo.footer.* --}}
@php
    $eco = config('nexo-ecosystem', []);
    $attrLabel = config('nexo.attribution.label') ?: 'made with Nexo ID';
    $attrUrl = config('nexo.attribution.url') ?: ($eco['github_org_url'] ?? 'https://github.com/nexo-tools');
@endphp

<footer {{ $attributes->merge(['class' => 'nexo-footer']) }}>
    <span class="nexo-footer__eco">
        <a href="{{ $eco['hub_url'] ?? 'https://nexotools.alvarocdev.com' }}" rel="noopener">
            {{ __('nexo.footer.part_of') }}
        </a>
    </span>

    <span class="nexo-footer__spacer"></span>

    <span>
        <a href="{{ $attrUrl }}" rel="noopener">{{ $attrLabel }}</a>
    </span>

    <a href="{{ $eco['github_org_url'] ?? 'https://github.com/nexo-tools' }}" rel="noopener">
        {{ __('nexo.footer.source') }}
    </a>

    {{-- Local addition to the shared component: this tool is the identity provider
         for the whole ecosystem — it holds the email, the password hash and the
         sessions of every account — so the legal pages are reachable from anywhere,
         the sign-in and consent screens included. --}}
    <a href="{{ route('legal.privacy') }}">{{ __('Privacy') }}</a>
    <a href="{{ route('legal.terms') }}">{{ __('Terms') }}</a>
</footer>
