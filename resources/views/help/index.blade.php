@extends('layouts.app')

@section('title', __('nexo.help.title'))

@section('head-meta')
    <meta name="description" content="{{ __('help.meta_description') }}">
    <link rel="canonical" href="{{ route('help') }}">
@endsection

@section('content')
    <div class="nexo-help">
        <h1>{{ __('nexo.help.title') }}</h1>
        <p>{{ __('nexo.help.intro') }}</p>

        @foreach ((array) __('help.faqs') as $faq)
            <details class="nexo-help__item">
                <summary>{{ $faq['q'] ?? '' }}</summary>
                <div>{!! $faq['a'] ?? '' !!}</div>
            </details>
        @endforeach

        <div class="nexo-help__item nexo-help__contact">
            <div>
                <strong>{{ __('nexo.help.contact_title') }}</strong>
                <p>
                    <a class="nexo-btn nexo-btn--primary" href="{{ $contactUrl }}">
                        {{ __('nexo.help.contact_cta') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
@endsection
