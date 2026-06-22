<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Manufacturer extends Model
{
    protected $fillable = [
        'name', 'short_name', 'contact', 'support_phone',
        'support_email', 'country_of_origin', 'notes', 'status',
    ];

    /**
     * Seed one manufacturer row for each distinct, non-empty manufacturer value
     * already stored on assets that isn't managed yet. Idempotent. Returns the
     * number of rows created.
     */
    public static function backfillFromAssets(): int
    {
        $existing = static::pluck('name')->all();

        $names = DB::table('assets')
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->distinct()
            ->pluck('manufacturer')
            ->reject(fn ($name) => in_array($name, $existing, true))
            ->unique()
            ->values();

        foreach ($names as $name) {
            static::create(['name' => $name, 'status' => 'active']);
        }

        return $names->count();
    }
}
