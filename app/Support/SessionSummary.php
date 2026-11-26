<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** One row of «Sesiones activas» on /profile (AC-SESS-1, H-nexoid-06). */
final readonly class SessionSummary
{
    /**
     * @param  array<int, string>  $tools  the Nexo tools this session signed in to
     */
    public function __construct(
        public string $id,
        public ?string $ipAddress,
        public ?string $userAgent,
        public Carbon $lastActive,
        public bool $isCurrent,
        public ?string $browser = null,
        public ?string $system = null,
        public array $tools = [],
    ) {}

    /** «Chrome en Windows», «Safari», «Dispositivo desconocido». */
    public function deviceLabel(): string
    {
        return match (true) {
            $this->browser !== null && $this->system !== null => __(':browser on :system', ['browser' => $this->browser, 'system' => $this->system]),
            $this->browser !== null => $this->browser,
            $this->system !== null => $this->system,
            default => __('Unknown device'),
        };
    }
}
