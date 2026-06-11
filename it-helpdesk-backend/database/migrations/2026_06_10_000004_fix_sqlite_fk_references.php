<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * When migration 003 renamed `tickets` → `_tickets_old`, modern SQLite
     * (3.26+) cascade-updated FK references in every child table so they now
     * point to `_tickets_old` instead of `tickets`. After _tickets_old was
     * dropped those FK checks fail with "no such table: main._tickets_old".
     *
     * Fix: rebuild each affected child table with corrected FK definitions.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        // Find every table whose CREATE SQL references _tickets_old
        $broken = DB::select(
            "SELECT name FROM sqlite_master WHERE type='table' AND sql LIKE '%_tickets_old%'"
        );

        if (empty($broken)) {
            return; // Already clean — nothing to do
        }

        DB::statement('PRAGMA foreign_keys=OFF;');
        DB::statement('PRAGMA legacy_alter_table=ON;');

        foreach ($broken as $row) {
            $table = $row->name;

            // Get the current (broken) CREATE SQL
            $schemRow = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
                [$table]
            );

            if (!$schemRow) {
                continue;
            }

            // Replace every reference to _tickets_old with tickets
            $fixedSql = str_replace(
                ['"_tickets_old"', '`_tickets_old`', "'_tickets_old'", '_tickets_old'],
                ['"tickets"',      '`tickets`',      "'tickets'",      'tickets'],
                $schemRow->sql
            );

            // Rename the broken table, recreate it with the fixed schema, copy data, drop old
            DB::statement("ALTER TABLE \"{$table}\" RENAME TO \"_{$table}_broken\";");
            DB::statement($fixedSql);
            DB::statement("INSERT INTO \"{$table}\" SELECT * FROM \"_{$table}_broken\";");
            DB::statement("DROP TABLE \"_{$table}_broken\";");
        }

        DB::statement('PRAGMA legacy_alter_table=OFF;');
        DB::statement('PRAGMA foreign_keys=ON;');
    }

    public function down(): void
    {
        // Irreversible repair — no rollback needed
    }
};
