<?php

namespace App\Entities;

use App\Models\User;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Entities\Traits\WithCustomPermittedFor;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

/**
 * Maps a Nexo ID user to OIDC claims. The bridge filters these by the access
 * token's granted scopes (profile → name, email → email/email_verified), so
 * this returns the full claim set and the extractor picks what the scopes allow.
 */
class IdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;
    use WithCustomPermittedFor;

    protected User $user;

    public function setIdentifier(mixed $identifier): void
    {
        $this->identifier = $identifier;
        $this->user = User::findOrFail($identifier);
    }

    /**
     * @param  string[]  $scopes
     * @return array<string, mixed>
     */
    public function getClaims(array $scopes = []): array
    {
        return [
            // profile scope
            'name' => $this->user->display_name,

            // email scope
            'email' => $this->user->email,
            'email_verified' => $this->user->hasVerifiedEmail(),
        ];
    }
}
