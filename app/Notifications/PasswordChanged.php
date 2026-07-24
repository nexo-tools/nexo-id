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
        return (new MailMessage)
            ->subject(__('Your :app password was changed', ['app' => config('app.name')]))
            ->line(__('The password for your account was just changed.'))
            ->line(__('If this was not you, reset your password immediately and review your active sessions.'));
    }
}
