<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset, in this product's template and language — same two reasons as
 * {@see VerifyEmailQueued}. This is the mail that gets somebody back into the
 * account that opens every other tool in the ecosystem, so it is the last one
 * that should arrive in a language they may not read.
 */
class ResetPasswordQueued extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /** @param  mixed  $notifiable */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->view('emails.reset-password', [
                'url' => url(route('password.reset', [
                    'token' => $this->token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false)),
                'expiresIn' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
