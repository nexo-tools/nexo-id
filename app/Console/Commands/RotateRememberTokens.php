<?php

namespace App\Console\Commands;

use App\Auth\RememberTokens;
use Illuminate\Console\Command;

/**
 * Cut every «remember me» cookie in existence (ADR-010, D4). Open sessions are
 * not touched; what stops working is the long-lived cookie that revives an
 * expired one. The deploy of the 30-day policy ran this once through migration
 * 2026_11_26_000003; the command is for doing it again on purpose (a suspected
 * leak, an incident). Idempotent: a second run finds nothing to rotate.
 */
class RotateRememberTokens extends Command
{
    protected $signature = 'nexo:rotate-remember-tokens';

    protected $description = 'Invalidate every «remember me» cookie (accounts stay signed in where they already are)';

    public function handle(RememberTokens $tokens): int
    {
        $count = $tokens->invalidateAll();

        $this->info("Rotated {$count} remember token(s).");

        return self::SUCCESS;
    }
}
