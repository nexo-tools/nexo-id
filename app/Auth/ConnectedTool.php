<?php

namespace App\Auth;

use Illuminate\Support\Carbon;

/** One row of «Herramientas conectadas» (see ConnectedTools). */
final readonly class ConnectedTool
{
    public function __construct(
        public string $clientId,
        public string $name,
        public ?string $mark,
        public ?string $openUrl,
        public ?Carbon $lastUsedAt,
    ) {}
}
