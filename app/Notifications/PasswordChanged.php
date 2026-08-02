<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued so the outbound "your password changed" mail never blocks the
 * password-change request (ChangeUserPassword sends it after mutating state).
 */
class PasswordChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // A view, not ->line(): the markdown wrapper builds its shell from the
        // framework's English strings ("Hello!", "Regards"), which this
        // project's i18n cannot reach — so a translated body arrived inside an
        // untranslated frame that looked nothing like the family's mail.
        return (new MailMessage)
            ->subject(__('Your :app password was changed', ['app' => config('app.name')]))
            ->view('emails.password-changed');
    }
}
