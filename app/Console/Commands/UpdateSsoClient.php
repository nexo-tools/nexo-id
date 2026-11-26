<?php

namespace App\Console\Commands;

use App\Models\OauthClient;
use App\Support\SsoClientUris;
use Illuminate\Console\Command;

/**
 * Change an existing client's back-channel logout URI or add redirect URIs —
 * what each tool needs when it adopts session control (ADR-010): register
 * `<tool>/auth/nexo/backchannel-logout` once that endpoint is live, and
 * `<tool>/auth/nexo/signed-out` as a post-logout destination (end_session only
 * honours registered redirect URIs).
 *
 * Idempotent: setting what is already set, or adding a redirect that is
 * already there, changes nothing. Always prints the client's final state.
 */
class UpdateSsoClient extends Command
{
    protected $signature = 'nexo:sso-client:update
        {client_id : The client id (uuid) printed by nexo:sso-client}
        {--backchannel= : Set the back-channel logout URI}
        {--remove-backchannel : Stop sending back-channel logout tokens to this client}
        {--add-redirect=* : Add an allowed redirect URI (repeatable)}';

    protected $description = 'Update an SSO client: back-channel logout URI and extra redirect URIs (idempotent)';

    public function handle(): int
    {
        $client = OauthClient::query()->find((string) $this->argument('client_id'));

        if ($client === null) {
            $this->error('No client with that id.');

            return self::FAILURE;
        }

        $backchannel = $this->option('backchannel');
        $addRedirects = array_values(array_filter((array) $this->option('add-redirect')));

        if (is_string($backchannel) && $this->option('remove-backchannel')) {
            $this->error('Use either --backchannel or --remove-backchannel, not both.');

            return self::FAILURE;
        }

        foreach (array_filter([is_string($backchannel) ? $backchannel : null, ...$addRedirects]) as $uri) {
            if (! SsoClientUris::valid($uri)) {
                $this->error("Not an absolute http(s) URL: {$uri}");

                return self::FAILURE;
            }
        }

        $changes = [];

        if (is_string($backchannel) && $client->backchannel_logout_uri !== $backchannel) {
            $changes['backchannel_logout_uri'] = $backchannel;
        }

        if ($this->option('remove-backchannel') && $client->backchannel_logout_uri !== null) {
            $changes['backchannel_logout_uri'] = null;
        }

        $redirects = (array) $client->redirect_uris;
        $missing = array_values(array_diff(array_unique($addRedirects), $redirects));

        if ($missing !== []) {
            $changes['redirect_uris'] = [...$redirects, ...$missing];
        }

        if ($changes !== []) {
            $client->forceFill($changes)->save();
            $client->refresh();
            $this->info('Client updated.');
        } else {
            $this->info('Nothing to change.');
        }

        $this->line('  client_id: '.$client->getKey());
        $this->line('  name: '.$client->name);
        $this->line('  redirects: '.implode(', ', (array) $client->redirect_uris));
        $this->line('  backchannel: '.($client->backchannel_logout_uri ?? '(none)'));
        $this->line('  first-party: '.($client->firstParty() ? 'yes' : 'no').' · revoked: '.($client->revoked ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
