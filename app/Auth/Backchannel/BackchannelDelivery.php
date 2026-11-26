<?php

namespace App\Auth\Backchannel;

/**
 * One logout_token to send: to whom (client + its back-channel URI), about
 * whom (sub) and which IdP session (sid; null = every session of that sub, the
 * account-deletion case).
 */
final readonly class BackchannelDelivery
{
    public function __construct(
        public string $clientId,
        public string $uri,
        public string $sub,
        public ?string $sid,
    ) {}

    public function key(): string
    {
        return $this->clientId.'|'.$this->sub.'|'.($this->sid ?? '*');
    }
}
