<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regression guard for the migration
 * 2026_07_22_013500_fix_oauth_user_id_columns_for_uuid_users.
 *
 * Passport's published migrations ship bigint user/owner columns, but our users
 * use uuid primary keys. sqlite (the default test driver) is dynamically typed
 * and never surfaces the mismatch, so the bug was invisible in tests. Real
 * MySQL/MariaDB truncates the uuid — silently on non-strict servers. This test
 * only runs when the suite is pointed at MySQL (CI), where the type is enforced.
 */
it('AC-UUID-1: oauth user/owner id columns are char(36) under MySQL (regression: bigint truncated uuids)', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Column-type regression is only observable on MySQL/MariaDB; sqlite is dynamically typed.');
    }

    $cases = [
        ['oauth_access_tokens', 'user_id'],
        ['oauth_auth_codes', 'user_id'],
        ['oauth_clients', 'owner_id'],
    ];

    foreach ($cases as [$table, $column]) {
        $definition = collect(Schema::getColumns($table))
            ->firstWhere('name', $column);

        expect($definition)->not->toBeNull("{$table}.{$column} should exist");

        // uuid columns are char(36); the pre-fix bug left them bigint, which
        // silently truncated uuid primary keys on non-strict MySQL/MariaDB.
        expect(strtolower((string) $definition['type_name']))->toBe('char')
            ->and(strtolower((string) $definition['type']))->toContain('36');
    }
});
