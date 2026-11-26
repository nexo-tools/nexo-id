<?php

namespace App\Jobs;

use App\Auth\Backchannel\BackchannelDelivery;
use App\Auth\Backchannel\BackchannelLogoutDispatcher;
use App\Auth\Backchannel\LogoutTokenIssuer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * A back-channel logout that did not land inline (ADR-010): retried with
 * backoff — 1 min (the dispatch delay), then 5, 15 and 60 minutes, and hourly
 * after that — until 24 h after the logout. Each attempt mints a fresh token
 * (a logout_token lives 120 s, a retry can come hours later).
 *
 * The queue on shared hosting is drained by the scheduler every minute
 * (routes/console.php); its --tries=3 does not cut this job short, because a
 * job with retryUntil() is retried until that moment instead.
 */
class DeliverBackchannelLogout implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public function __construct(
        public BackchannelDelivery $delivery,
        public string $issuer,
        public int $deadline,
    ) {}

    /** @return array<int, int> seconds between attempts */
    public function backoff(): array
    {
        return [300, 900, 3600];
    }

    public function retryUntil(): Carbon
    {
        return Carbon::createFromTimestamp($this->deadline);
    }

    public function handle(LogoutTokenIssuer $tokens): void
    {
        if (! config('nexo.backchannel.enabled', true)) {
            return;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post($this->delivery->uri, ['logout_token' => $tokens->issue($this->delivery, $this->issuer)]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Back-channel logout: client unreachable ('.$e->getMessage().').', previous: $e);
        }

        if ($response->successful()) {
            return;
        }

        if (BackchannelLogoutDispatcher::isPermanentRefusal($response->status())) {
            Log::warning('Back-channel logout refused by the client; not retrying.', [
                'client_id' => $this->delivery->clientId,
                'status' => $response->status(),
            ]);

            return;
        }

        throw new RuntimeException('Back-channel logout: client answered '.$response->status().'.');
    }
}
