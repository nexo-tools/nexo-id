<?php

namespace App\Auth\Backchannel;

use App\Jobs\DeliverBackchannelLogout;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans a set of logout_tokens out to the tools (ADR-010, contract §2.3).
 *
 * One synchronous attempt per delivery, all in parallel with a
 * `nexo.backchannel.timeout` (2 s) budget, so the person who signed out waits
 * ~2 s at worst however many tools there are. Whatever fails transiently (no
 * connection, timeout, 5xx, 429) goes to the queue with backoff for up to 24 h.
 * A definitive refusal (another 4xx: the tool rejected the token or has its
 * endpoint switched off) is logged and dropped — retrying cannot fix it, and
 * the tool's 15-minute re-validation still closes that session.
 *
 * With more than `nexo.backchannel.sync_max` deliveries nothing runs inline:
 * everything is queued. With NEXO_OIDC_BACKCHANNEL=false nothing is sent.
 */
class BackchannelLogoutDispatcher
{
    public function __construct(private LogoutTokenIssuer $tokens) {}

    /**
     * @param  Collection<int, BackchannelDelivery>  $deliveries
     */
    public function dispatch(Collection $deliveries): void
    {
        if (! config('nexo.backchannel.enabled', true)) {
            return;
        }

        $deliveries = $deliveries->unique(fn (BackchannelDelivery $d): string => $d->key())->values();

        if ($deliveries->isEmpty()) {
            return;
        }

        $issuer = LogoutTokenIssuer::currentIssuer();

        if ($deliveries->count() > (int) config('nexo.backchannel.sync_max', 10)) {
            $deliveries->each(fn (BackchannelDelivery $d) => $this->queue($d, $issuer, delaySeconds: 0));

            return;
        }

        $timeout = (int) config('nexo.backchannel.timeout', 2);

        try {
            $results = Http::pool(fn (Pool $pool) => $deliveries->map(
                fn (BackchannelDelivery $d, int $i) => $pool->as((string) $i)
                    ->asForm()
                    ->connectTimeout($timeout)
                    ->timeout($timeout)
                    ->post($d->uri, ['logout_token' => $this->tokens->issue($d, $issuer)])
            )->all());
        } catch (Throwable $e) {
            // The pool itself blew up (not a per-request failure): never let a
            // logout fail because of it — everything goes to the queue.
            Log::warning('Back-channel logout: inline fan-out failed, queuing all.', ['error' => $e->getMessage()]);
            $deliveries->each(fn (BackchannelDelivery $d) => $this->queue($d, $issuer, delaySeconds: 60));

            return;
        }

        foreach ($deliveries as $i => $delivery) {
            $result = $results[(string) $i] ?? null;

            if ($result instanceof Response && $result->successful()) {
                continue;
            }

            if ($result instanceof Response && self::isPermanentRefusal($result->status())) {
                Log::warning('Back-channel logout refused by the client; not retrying.', [
                    'client_id' => $delivery->clientId,
                    'status' => $result->status(),
                ]);

                continue;
            }

            // First retry one minute later; the job's own backoff takes it
            // from there (5, 15, 60 min… until 24 h).
            $this->queue($delivery, $issuer, delaySeconds: 60);
        }
    }

    /** A 4xx other than 408/429 means "this token, never": retrying won't help. */
    public static function isPermanentRefusal(int $status): bool
    {
        return $status >= 400 && $status < 500 && ! in_array($status, [408, 429], true);
    }

    private function queue(BackchannelDelivery $delivery, string $issuer, int $delaySeconds): void
    {
        DeliverBackchannelLogout::dispatch($delivery, $issuer, now()->addDay()->getTimestamp())
            ->delay($delaySeconds > 0 ? now()->addSeconds($delaySeconds) : null);
    }
}
