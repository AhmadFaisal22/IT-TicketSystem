<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        if (Asset::count() > 0) {
            return;
        }

        $holder = User::query()->inRandomOrder()->first();

        Asset::factory()->count(15)->create();
        Asset::factory()->count(5)->create([
            'status'      => 'assigned',
            'assigned_to' => $holder?->id,
        ]);
        Asset::factory()->count(2)->create(['status' => 'in_repair']);
    }
}
