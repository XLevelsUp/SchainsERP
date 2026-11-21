<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Found 2026-11-20 on Aiven: the roles were seeded with explicit ids (1–5)
 * but the id sequence was left at 1, so the first role created through the
 * API failed with "duplicate key value violates unique constraint
 * roles_pkey (id)=(1)". Moves the sequence past the highest id.
 *
 * Only advances a sequence; rows are untouched. Safe to run again. PostgreSQL
 * only (SQLite has no sequences to drift). Nothing to undo on rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            SELECT setval(
                pg_get_serial_sequence('roles', 'id'),
                GREATEST((SELECT COALESCE(MAX(id), 0) FROM roles), 1),
                (SELECT COUNT(*) > 0 FROM roles)
            )
        SQL);
    }

    public function down(): void
    {
        // Intentionally empty: rewinding the sequence would only bring the
        // duplicate-key failure back.
    }
};
