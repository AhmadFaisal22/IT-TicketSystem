<?php

use App\Support\AssetCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_zh')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_zh')->nullable();
            $table->timestamps();
        });

        // Seed the categories that were previously hardcoded constants.
        $now = now();
        foreach (AssetCategories::DEFAULTS as $key => [$name, $nameZh]) {
            DB::table('asset_categories')->insert([
                'name' => $name, 'name_zh' => $nameZh, 'created_at' => $now, 'updated_at' => $now,
            ]);
            // Migrate existing asset rows from the old key to the display name.
            DB::table('assets')->where('category', $key)->update(['category' => $name]);
        }
    }

    public function down(): void
    {
        foreach (AssetCategories::DEFAULTS as $key => [$name, $nameZh]) {
            DB::table('assets')->where('category', $name)->update(['category' => $key]);
        }
        Schema::dropIfExists('asset_locations');
        Schema::dropIfExists('asset_categories');
    }
};
