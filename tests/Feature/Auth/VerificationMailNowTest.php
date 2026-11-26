<?php

use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

// «Verifica tu correo» used to wait for the queue, which the hosting's cron
// drains once a minute: whoever had just signed up from a tool stared at it for
// up to a minute. The mail now goes out right after the response (F3, 2026-11-26).

/** @return list<SentMessage> */
function sentVerificationMails(): array
{
    return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->all();
}

it('AC-REG-3: the verification mail goes out right after the sign-up response, not through the queue', function () {
    Queue::fake();

    $this->post('/register', [
        'display_name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ])->assertRedirect(route('verification.notice'));

    Queue::assertNotPushed(SendQueuedNotifications::class);
    $sent = sentVerificationMails();
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe('ada@example.test');
});

it('AC-REG-3: the sign-up never waits on the relay: nothing is sent until the response is done', function () {
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();
    expect(sentVerificationMails())->toHaveCount(0);

    app()->terminate();
    expect(sentVerificationMails())->toHaveCount(1);
});

it('AC-REG-3: if the relay fails, the verification mail waits in the queue, as before', function () {
    Queue::fake();
    Mail::extend('broken', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('relay down');
        }

        public function __toString(): string
        {
            return 'broken://';
        }
    });
    config(['mail.mailers.broken' => ['transport' => 'broken'], 'mail.default' => 'broken']);
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();
    app()->terminate();

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof VerifyEmailQueued
        && $job->notifiables->first()->is($user));
});
