@extends('layouts.app')

@section('seo')
    <x-nexo-seo :title="$title.' — '.config('app.name')" :description="$description" :canonical="url()->current()" />
@endsection

@section('content')
    <article class="flex flex-col">
        <h1 class="text-2xl font-semibold">{{ $content['title'] }}</h1>
        <p class="mt-1 text-xs text-muted">{{ $updated }}</p>

        <p class="mt-6 leading-relaxed text-ink">{{ $content['intro'] }}</p>

        @foreach ($content['sections'] as $section)
            <section class="mt-8">
                <h2 class="text-base font-semibold">{{ $section['h'] }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted">{{ $section['p'] }}</p>
            </section>
        @endforeach

        {{-- Who runs THIS instance. Env-backed so a self-host never inherits the
             upstream author's details; hidden entirely when unset. --}}
        @if ($operator !== '' || $contact !== '')
            <section class="mt-8">
                <h2 class="text-base font-semibold">{{ __('legal.operator.h') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted">
                    @if ($operator !== ''){{ __('legal.operator.p', ['operator' => $operator]) }} @endif
                    @if ($contact !== ''){{ __('legal.operator.contact', ['contact' => $contact]) }}@endif
                </p>
            </section>
        @endif

        <p class="mt-10 border-t border-line pt-4 text-sm">
            <a href="{{ route('legal.privacy') }}" class="text-primary underline hover:text-primary-hover">{{ __('Privacy') }}</a>
            ·
            <a href="{{ route('legal.terms') }}" class="text-primary underline hover:text-primary-hover">{{ __('Terms') }}</a>
            ·
            <a href="{{ route('help') }}" class="text-primary underline hover:text-primary-hover">{{ __('nexo.help.title') }}</a>
        </p>
    </article>
@endsection
