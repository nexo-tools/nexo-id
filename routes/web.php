<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    // Verification notice — registration lands here; the verify/resend logic
    // is added in task 1.5.
    Route::view('verify-email', 'auth.verify-email')->name('verification.notice');
});
