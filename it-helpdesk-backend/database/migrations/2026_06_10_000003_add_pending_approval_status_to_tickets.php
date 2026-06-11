<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: recreate the table to change the status CHECK constraint.
            // IMPORTANT: legacy_alter_table=ON prevents SQLite from cascade-updating
            // FK references in child tables (ticket_histories, comments, etc.) when
            // we rename tickets→_tickets_old. Without it, child tables end up pointing
            // to _tickets_old instead of tickets after we drop _tickets_old.
            DB::statement('PRAGMA foreign_keys=OFF;');
            DB::statement('PRAGMA legacy_alter_table=ON;');
            DB::statement('DROP TABLE IF EXISTS _tickets_old;');
            DB::statement('ALTER TABLE tickets RENAME TO _tickets_old;');
            DB::statement("
                CREATE TABLE tickets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    ticket_number VARCHAR(255) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NOT NULL,
                    status VARCHAR(255) CHECK(status IN (
                        'pending_approval','open','in_progress','pending','resolved','closed','rejected'
                    )) NOT NULL DEFAULT 'open',
                    priority VARCHAR(255) CHECK(priority IN ('low','medium','high','critical')) NOT NULL DEFAULT 'medium',
                    category VARCHAR(255) NULL,
                    subcategory VARCHAR(255) NULL,
                    department_id INTEGER NOT NULL REFERENCES departments(id),
                    created_by INTEGER NOT NULL REFERENCES users(id),
                    assigned_to INTEGER NULL REFERENCES users(id),
                    sla_response_due_at DATETIME NULL,
                    sla_resolution_due_at DATETIME NULL,
                    first_response_at DATETIME NULL,
                    resolved_at DATETIME NULL,
                    closed_at DATETIME NULL,
                    sla_response_breached TINYINT(1) NOT NULL DEFAULT 0,
                    sla_resolution_breached TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                )
            ");
            DB::statement('INSERT INTO tickets (id,ticket_number,title,description,status,priority,category,subcategory,department_id,created_by,assigned_to,sla_response_due_at,sla_resolution_due_at,first_response_at,resolved_at,closed_at,sla_response_breached,sla_resolution_breached,created_at,updated_at) SELECT id,ticket_number,title,description,status,priority,category,subcategory,department_id,created_by,assigned_to,sla_response_due_at,sla_resolution_due_at,first_response_at,resolved_at,closed_at,sla_response_breached,sla_resolution_breached,created_at,updated_at FROM _tickets_old;');
            DB::statement('DROP TABLE _tickets_old;');
            DB::statement('PRAGMA legacy_alter_table=OFF;');
            DB::statement('PRAGMA foreign_keys=ON;');
        } else {
            // PostgreSQL: update the check constraint
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check;');
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('pending_approval','open','in_progress','pending','resolved','closed','rejected'));");
        }
    }

    public function down(): void
    {
        // Reversing this migration would lose data; skip
    }
};
