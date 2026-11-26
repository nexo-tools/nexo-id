<?php

namespace App\Http\Controllers\Oidc;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenIDConnect\Laravel\DiscoveryController;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;

/**
 * Wraps the OIDC bridge's discovery document to advertise S256-only PKCE and,
 * since ADR-010, back-channel logout. The bridge hardcodes ['plain', 'S256'] in
 * code_challenge_methods_supported, but we reject 'plain' at /oauth/authorize
 * (EnforcePkceS256), so the metadata must not offer it. This replaces the
 * bridge's built-in discovery route, which is disabled via
 * config('openid.routes.discovery') = false.
 */
class OpenIdConfigurationController
{
    public function __invoke(
        Request $request,
        LaravelCurrentRequestService $currentRequestService,
        DiscoveryController $discovery,
    ): JsonResponse {
        $response = $discovery($request, $currentRequestService);

        $data = $response->getData(true);
        $data['code_challenge_methods_supported'] = ['S256'];

        // OIDC Back-Channel Logout 1.0 (ADR-010): logout_tokens carry the sid
        // the id_token announced. Off with NEXO_OIDC_BACKCHANNEL=false.
        if (config('nexo.backchannel.enabled', true)) {
            $data['backchannel_logout_supported'] = true;
            $data['backchannel_logout_session_supported'] = true;
        }

        return response()->json($data, 200, [], JSON_PRETTY_PRINT);
    }
}
