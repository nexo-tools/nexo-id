{{-- Shared <head> for every shell: layouts.app, layouts.auth's public sibling and
     the shells Laravel renders outside a layout (the error pages), so the favicons,
     the theme init and the bundle are declared in one place only.

     Title precedence: a page inside layouts.app can own the whole SEO head via the
     `seo` section (<x-nexo-seo> emits its own <title>); otherwise the `title`
     section wins, then a $title handed over by the including view (the shells with
     no section stack), then the app name. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="referrer" content="strict-origin-when-cross-origin">
@hasSection('seo')
    @yield('seo')
@else
    <title>@yield('title', $title ?? config('app.name'))</title>
@endif
@yield('head-meta')
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
{{-- Stamp <html data-theme> before the stylesheet loads (no FOUC). --}}
@include('partials.theme-init')
@include('partials.beacon')
@vite(['resources/css/app.css', 'resources/js/app.js'])
