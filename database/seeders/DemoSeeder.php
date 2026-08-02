<?php

namespace Database\Seeders;

use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the landing screenshots (design.md, "Family": real captures from
 * a LOCAL instance, never production). An identity provider's product is the
 * three screens a person actually meets — the account being created, an app
 * asking for permission, and the list of devices you are signed in on — so the
 * fixture has to make all three show something.
 *
 * Deliberately NOT registered in DatabaseSeeder: it is run explicitly
 * (`artisan db:seed --class=DemoSeeder`) by whoever is re-capturing.
 *
 * Deterministic on purpose: same user, same client id, same session rows on
 * every run, because a screenshot that changes when nothing changed is a diff
 * nobody can review. The one exception is the "last active" column, which reads
 * as «hace X minutos» and therefore moves with the clock.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A fixed id, not a generated one: the consent capture navigates to
     * /oauth/authorize?client_id=…, so the manifest and the seeder have to
     * agree on the value.
     */
    private const CLIENT_ID = '99999999-9999-4999-8999-999999999901';

    /**
     * Three devices, so "Active sessions" is a list rather than a single row.
     * The IPs are from the documentation range (RFC 5737) — no real address of
     * anyone's ends up in a published screenshot. last_activity stays inside
     * the session lifetime window: older rows are fair game for the session
     * garbage collector, which would empty the figure halfway through a run.
     *
     * @var list<array{id:string, ip:string, agent:string, minutesAgo:int}>
     */
    private const SESSIONS = [
        [
            'id' => 'demo-session-iphone-0000000000000001',
            'ip' => '203.0.113.10',
            'agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1',
            'minutesAgo' => 5,
        ],
        [
            'id' => 'demo-session-macbook-000000000000002',
            'ip' => '203.0.113.20',
            'agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
            'minutesAgo' => 40,
        ],
        [
            'id' => 'demo-session-windows-000000000000003',
            'ip' => '203.0.113.30',
            'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:135.0) Gecko/20100101 Firefox/135.0',
            'minutesAgo' => 100,
        ],
    ];

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['display_name' => 'Demo', 'password' => Hash::make('password')],
        );

        // /profile sits behind the `verified` middleware: without this the
        // capture run signs in and lands on the verification notice instead of
        // the account page the figure is supposed to show. Fixed date, not
        // now(), so re-seeding does not move it.
        $user->forceFill(['email_verified_at' => now()->subDays(45)->startOfDay()])->save();

        // No Eloquent model for the session store — it is the framework's own
        // table, written by the database session driver. payload is NOT NULL
        // and the listing never reads it, so an empty serialized array is
        // enough to satisfy the column.
        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

        foreach (self::SESSIONS as $session) {
            DB::table(config('session.table', 'sessions'))->insert([
                'id' => $session['id'],
                'user_id' => $user->id,
                'ip_address' => $session['ip'],
                'user_agent' => $session['agent'],
                'payload' => base64_encode(serialize([])),
                'last_activity' => now()->subMinutes($session['minutesAgo'])->getTimestamp(),
            ]);
        }

        // A third-party client — one WITH an owner. skipsAuthorization() returns
        // firstParty(), which is "no owner", so a client without an owner would
        // sail through consent silently and the second figure would have nothing
        // to photograph. forceCreate, and the id written by hand, because the
        // model only generates a UUID when the attribute is empty and the
        // capture manifest needs this exact value.
        OauthClient::query()->whereKey(self::CLIENT_ID)->delete();

        $client = new OauthClient;
        $client->forceFill([
            'id' => self::CLIENT_ID,
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
            'name' => 'Demo App',
            'secret' => null,
            'provider' => null,
            'revoked' => false,
            'redirect_uris' => ['https://client.example/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
        ])->save();
    }
}
