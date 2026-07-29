<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Oidc\EndSessionController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// Public help center (no catch-all in this app, but registered here in the
// public block so it can never be shadowed). Uses layouts.app + the Nexo chrome.
Route::get('/help', HelpController::class)->name('help');

// Legal pages. This tool is the identity provider: it holds the email, the
// password hash, the sessions and the tool authorizations of every ecosystem
// account, so privacy + terms are not optional here.
//
// Paths are Spanish (the ecosystem is Spanish-first); the ROUTE NAMES are not —
// `legal.privacy` / `legal.terms` are what the footer, the sitemap and the
// StaticPagesTest guardian reference across every tool. Public on purpose: they
// must be readable while logged out and indexable.
Route::get('/privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terminos', [LegalController::class, 'terms'])->name('legal.terms');

// OIDC RP-initiated (front-channel) logout. Lives in web.php (not oidc.php) so it
// runs inside the session middleware and can end the browser session; the route
// NAME is what the discovery controller advertises as end_session_endpoint. It
// validates post_logout_redirect_uri against the id_token_hint client's URIs and
// never open-redirects. (ADR-009 / M4b)
Route::get('/oauth/logout', EndSessionController::class)->name('openid.end_session_endpoint');

Route::get('/sitemap.xml', function () {
    // Every public, indexable page. The auth and OAuth surface is disallowed in
    // robots.txt and must never be listed here.
    $urls = [url('/'), route('help'), route('legal.privacy'), route('legal.terms')];

    $xml = cache()->remember('sitemap', now()->addHour(), fn (): string => '<?xml version="1.0" encoding="UTF-8"?>'
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .implode('', array_map(fn (string $url): string => '<url><loc>'.e($url).'</loc></url>', $urls))
        .'</urlset>');

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::get('/robots.txt', function () {
    $lines = [
        'User-agent: *',
        'Disallow: /login',
        'Disallow: /register',
        'Disallow: /forgot-password',
        'Disallow: /reset-password/',
        'Disallow: /verify-email',
        'Disallow: /profile',
        'Disallow: /oauth/',
        'Disallow: /email/',
        '',
        'Sitemap: '.route('sitemap'),
    ];

    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
})->name('robots');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login-ip');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');
        Route::delete('profile/sessions/{id}', [SessionController::class, 'destroy'])->name('sessions.destroy');
        Route::delete('profile/sessions', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
    });
});
