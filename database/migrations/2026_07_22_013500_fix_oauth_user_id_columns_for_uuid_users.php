<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Passport's published migrations ship bigint user columns, but our users use
// uuid primary keys. sqlite (tests) never enforces this; real MySQL/MariaDB
// truncates the uuid — silently on non-strict servers. Found by the Phase 3
// real-provider e2e (task 3.3). Auth codes/tokens are short-lived, so no data
// is worth preserving through the type change.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            $table->uuid('user_id')->change();
        });
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
        });
        Schema::table('oauth_device_codes', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
        });
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->uuid('owner_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            $table->foreignId('user_id')->change();
        });
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
        Schema::table('oauth_device_codes', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->change();
        });
    }
};
