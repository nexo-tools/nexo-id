<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email verification, in this product's template and language.
 *
 * The framework default was going out in English on Laravel's markdown wrapper:
 * those strings live in the framework's own translations, which this project's
 * i18n cannot reach. The identity provider of a Spanish-first ecosystem was
 * mailing "Verify Email Address" — the first thing a new account ever sees.
 *
 * Queued for the family reason (C2): a slow relay must not turn a sign-up into
 * a failed request for an account that was in fact created.
 */
class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /** @param  mixed  $notifiable */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Verify your email'))
            ->view('emails.verify-email', ['url' => $this->verificationUrl($notifiable)]);
    }
}
