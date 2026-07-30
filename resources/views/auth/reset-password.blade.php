@extends('layouts.auth')

@section('title', __('Reset password'))
@section('heading', __('Choose a new password'))

@section('content')
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-field name="email" type="email" :label="__('Email')" :value="$email" autocomplete="email" />
        <x-field name="password" type="password" :label="__('New password')" autocomplete="new-password" />
        <x-field name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />

        <button type="submit"
                class="nexo-btn nexo-btn--primary w-full">
            {{ __('Reset password') }}
        </button>
    </form>
@endsection
