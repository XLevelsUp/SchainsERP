<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BACKEND_TODO §24: Sale Gold "In Cash Converter" writes a stock row with
 * entry_type IN_CASH_CONVERTER (SaleGoldService), but the entry_type enum
 * (a CHECK constraint on PostgreSQL) didn't allow it, so every such save
 * failed and rolled back. Adds the value on both stock tables, which share
 * the same list.
 */
return new class extends Migration
{
    private const TABLES = ['stock_details', 'stock_in_details'];

    private const BEFORE = [
        'NORMAL', 'EMPTOEMP', 'EMPTOHEAD', 'ANOTHERHEADTOEMP', 'HEADTOHEAD',
        'GOLDCASHCONVERSION', 'OUT_CASH_CONVERTER', 'CashToGold', 'SALES',
    ];

    public function up(): void
    {
        $this->setAllowed([...self::BEFORE, 'IN_CASH_CONVERTER']);
    }

    /**
     * Fails if any IN_CASH_CONVERTER rows exist by then, rather than silently
     * dropping or rewriting them.
     */
    public function down(): void
    {
        $this->setAllowed(self::BEFORE);
    }

    private function setAllowed(array $values): void
    {
        foreach (self::TABLES as $table) {
            if (DB::getDriverName() === 'pgsql') {
                $constraint = "{$table}_entry_type_check";
                $list = implode(', ', array_map(fn ($v) => "'{$v}'", $values));
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (entry_type IN ({$list}))");
            } else {
                // SQLite (the test database) rebuilds the table with the new CHECK.
                Schema::table($table, function (Blueprint $t) use ($values) {
                    $t->enum('entry_type', $values)->default('NORMAL')->change();
                });
            }
        }
    }
};
