<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final readonly class SessionSummary
{
    public function __construct(
        public string $id,
        public ?string $ipAddress,
        public ?string $userAgent,
        public Carbon $lastActive,
        public bool $isCurrent,
    ) {}
}
