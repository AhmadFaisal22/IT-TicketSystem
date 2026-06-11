<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('purchase_date', 'assign_date');
            $table->string('purchase_link', 2048)->nullable()->after('purchase_cost');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('assign_date', 'purchase_date');
            $table->dropColumn('purchase_link');
        });
    }
};
