<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optimistic-locking version counter. Bumped on every guarded update; a stale
// value in the WHERE clause means a concurrent writer won, so the update is
// rejected (HTTP 409) instead of silently overwriting their changes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('version'));
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('version'));
    }
};
