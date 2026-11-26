<?php

namespace App\Console\Commands;

use App\Models\IdpSession;
use Illuminate\Console\Command;

/**
 * Keep the IdP session registry small (ADR-010): drop sessions that ended more
 * than 30 days ago, and open ones nobody used for 45 days — longer than the
 * 30-day «remember me», after which a device can only come back with a fresh
 * login, which starts a new sid. Their client rows go with them (cascade).
 * Scheduled daily (routes/console.php).
 */
class PruneIdpSessions extends Command
{
    protected $signature = 'nexo:prune-idp-sessions';

    protected $description = 'Delete ended and long-idle IdP sessions (sid registry)';

    public function handle(): int
    {
        $ended = IdpSession::query()->where('ended_at', '<', now()->subDays(30))->delete();

        $idle = IdpSession::query()
            ->whereNull('ended_at')
            ->where(fn ($query) => $query
                ->where('last_seen_at', '<', now()->subDays(45))
                ->orWhere(fn ($query) => $query->whereNull('last_seen_at')->where('created_at', '<', now()->subDays(45))))
            ->delete();

        $this->info("Pruned {$ended} ended and {$idle} idle IdP session(s).");

        return self::SUCCESS;
    }
}
