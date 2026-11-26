<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The session registry of the identity provider (ADR-010).
 *
 * idp_sessions: one row per Nexo ID login on a device — the OIDC `sid`, which
 * travels in every id_token issued during that login and is what a
 * back-channel logout_token names. Independent of Laravel's session id (that
 * one changes on regenerate; the sid does not).
 *
 * idp_session_clients: which tools obtained an authorization code during each
 * sid — exactly the set that has to hear about it when the sid ends.
 *
 * Both are new and empty: no data plan needed (DATABASE-STANDARD §11). Sessions
 * already open at deploy time get a sid lazily, on their next authorize.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idp_sessions', function (Blueprint $table) {
            // The contract fixes the sid as the key (it is the identifier the
            // whole protocol speaks), not a surrogate id (ADR-010).
            $table->string('sid', 64)->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // logout · end_session · session_revoked · other_sessions_revoked ·
            // all_devices · password_changed · password_reset · account_deleted
            $table->string('ended_reason', 32)->nullable();

            $table->index(['user_id', 'ended_at']);
        });

        Schema::create('idp_session_clients', function (Blueprint $table) {
            $table->id();
            $table->string('sid', 64);
            $table->foreignUuid('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->unique(['sid', 'client_id']);
            $table->foreign('sid')->references('sid')->on('idp_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_session_clients');
        Schema::dropIfExists('idp_sessions');
    }
};
