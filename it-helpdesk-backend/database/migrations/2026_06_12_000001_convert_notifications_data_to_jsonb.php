<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * notifications.data was created as TEXT, but TicketController::destroy
     * filters on `data->ticket_id`. PostgreSQL has no JSON operators for TEXT
     * columns (SQLite's json_extract tolerates them), so ticket deletion
     * 500'd in production. Convert to jsonb where it matters.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }
    }
};
