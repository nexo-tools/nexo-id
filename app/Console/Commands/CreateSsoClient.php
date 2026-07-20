<?php

namespace App\Console\Commands;

use App\Models\OauthClient;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;

class CreateSsoClient extends Command
{
    protected $signature = 'nexo:sso-client
        {name? : Human-readable client name (the tool)}
        {--redirect=* : Allowed redirect URI (repeatable); exact match on authorize}
        {--list : List existing first-party clients instead of creating one}';

    protected $description = 'Register a first-party public (PKCE) OAuth/OIDC client for a Nexo tool';

    public function handle(ClientRepository $clients): int
    {
        if ($this->option('list')) {
            return $this->listClients();
        }

        $name = (string) $this->argument('name');
        $redirects = array_values(array_filter((array) $this->option('redirect')));

        if ($name === '' || $redirects === []) {
            $this->error('A name argument and at least one --redirect URI are required.');

            return self::FAILURE;
        }

        // Public (no secret) + first-party (no owner) authorization-code client.
        $client = $clients->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: $redirects,
            confidential: false,
            user: null,
        );

        $this->info('First-party public client created.');
        $this->line('  client_id: '.$client->getKey());
        $this->line('  redirects: '.implode(', ', $redirects));
        $this->line('  PKCE required, no client secret (public client).');

        return self::SUCCESS;
    }

    private function listClients(): int
    {
        $rows = OauthClient::query()
            ->whereNull('owner_id')
            ->get(['id', 'name', 'redirect_uris'])
            ->map(fn (OauthClient $c): array => [
                $c->getKey(),
                $c->name,
                implode(' ', (array) $c->redirect_uris),
            ])
            ->all();

        $this->table(['client_id', 'name', 'redirects'], $rows);

        return self::SUCCESS;
    }
}
