<?php

namespace App\Models;

use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Throwable;

#[Fillable(['display_name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Normalize the email on write so uniqueness and lookups are
     * case-insensitive on every driver (AC-REG-2).
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => strtolower(trim($value)),
        );
    }

    /**
     * `name` is the display name under the name every other Nexo app (and the
     * OIDC `name` claim) uses for it. The shared chrome (templates/nexo-ui:
     * account menu, phone sheet, initials) and its guardians read and write
     * `name`, identical in the 6 apps; here the column is `display_name`.
     * Not a second column: reading and writing both land on display_name.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => (string) ($attributes['display_name'] ?? ''),
            set: fn (string $value): array => ['display_name' => $value],
        );
    }

    /**
     * Both auth mails go out in this product's template and language, with the
     * locale pinned at dispatch (family rules C2 and C3). The queue worker has
     * no request to read a locale from, which is exactly how the identity
     * provider of a Spanish-first ecosystem ended up mailing "Verify Email
     * Address" to every new account.
     *
     * users.locale stays reserved and unwritten: nothing in the product lets a
     * person choose a language yet, and guessing one from a request would make
     * the column lie. Documented decision, 2026-08-02.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPasswordQueued($token))->locale(app()->getLocale()));
    }

    /**
     * The verification mail goes out right after the response, not through the
     * queue: the hosting's cron drains the queue once a minute, so somebody who
     * had just created their account from a tool waited up to a minute in front
     * of «Verifica tu correo» (F3, 2026-11-26). The sign-up still never waits on
     * the mail relay (C2): the send runs once the response has been flushed
     * (terminating), and if the relay fails the queued notification retries it,
     * as before.
     */
    public function sendEmailVerificationNotification(): void
    {
        $locale = app()->getLocale();

        app()->terminating(function () use ($locale): void {
            try {
                $this->notifyNow((new VerifyEmailQueued)->locale($locale));
            } catch (Throwable $e) {
                Log::warning('Verification mail sent to the queue after a failed direct send.', [
                    'user' => $this->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $this->notify((new VerifyEmailQueued)->locale($locale));
            }
        });
    }
}
