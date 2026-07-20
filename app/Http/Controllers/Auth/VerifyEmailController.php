<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email as verified. The signed + expiring
     * link is validated by EmailVerificationRequest before this runs
     * (AC-VERIFY-1, AC-VERIFY-2).
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $target = route('profile.show').'?verified=1';

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($target);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended($target);
    }
}
