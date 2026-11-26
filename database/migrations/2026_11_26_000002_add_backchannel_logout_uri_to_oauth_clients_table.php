<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each client receives OIDC back-channel logout tokens (ADR-010).
 * Nullable and additive: existing clients keep working and receive nothing
 * until an operator registers their URI with `nexo:sso-client:update
 * <client_id> --backchannel=<url>` — which is done per tool only once its
 * endpoint is live. No data to migrate; the rollback drops the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->string('backchannel_logout_uri')->nullable()->after('redirect_uris');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('backchannel_logout_uri');
        });
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
