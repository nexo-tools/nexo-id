<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time cut of every «remember me» cookie issued before the 30-day policy
 * (decision D4 of 2026-11-26, ADR-010): until this release the cookie lasted
 * the framework default of ~400 days, and lowering the duration only applies to
 * cookies issued from now on.
 *
 * Data plan (DATABASE-STANDARD §11): nulls users.remember_token for every row
 * that has one. A null token never matches a recaller cookie, so each old
 * cookie stops working; the next «remember me» login sets a fresh token as
 * usual. Idempotent. Nobody is signed out of an open session — only the
 * long-lived cookie stops reviving expired ones.
 *
 * Why a migration and not only the command: the deploy runs `migrate --force`
 * exactly once per migration, so the cut happens in this deploy and never
 * again by accident. `php artisan nexo:rotate-remember-tokens` does the same on
 * demand (e.g. after an incident).
 *
 * Rollback: nothing to undo — the old tokens are gone by design.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    public function down(): void
    {
        // Irreversible by design (see the class docblock).
    }
};
