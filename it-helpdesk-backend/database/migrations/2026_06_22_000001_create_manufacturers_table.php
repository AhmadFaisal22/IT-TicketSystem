<?php

use App\Models\Manufacturer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // official name (stored on the asset)
            $table->string('short_name')->nullable();  // abbreviation
            $table->string('contact')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('support_email')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active | inactive
            $table->timestamps();
        });

        // Backfill from manufacturer values already present on assets so the
        // dropdown is populated and existing asset values stay valid.
        Manufacturer::backfillFromAssets();
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturers');
    }
};
