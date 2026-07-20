<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\Scope;

/**
 * @property array<int, string> $redirect_uris
 */
class OauthClient extends PassportClient
{
    /**
     * First-party clients (the Nexo tools, created without an owner) skip the
     * consent screen — that is what makes silent SSO consent-free (AC-AUTH-1).
     * Third-party clients (with an owner) still prompt; third-party consent UI
     * is backlog (see SPEC-sso "Out").
     *
     * @param  Scope[]  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->firstParty();
    }
}
