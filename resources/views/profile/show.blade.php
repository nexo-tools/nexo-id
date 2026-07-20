@extends('layouts.app')

@section('title', __('Your account'))

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('Your account') }}</h1>
    <p class="mt-2 text-neutral-400">{{ auth()->user()->email }}</p>
    {{-- Full profile (display name, password, sessions) arrives in task 1.8. --}}
@endsection
