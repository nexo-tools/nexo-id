@extends('layouts.auth')

@section('title', __('Verify your email'))
@section('heading', __('Verify your email'))

@section('content')
    <p class="text-sm text-neutral-400">
        {{ __('We sent a verification link to your email address. Click it to activate your account.') }}
    </p>
@endsection
