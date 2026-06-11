<?php

namespace Database\Factories;

use App\Support\AssetCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        $faker = fake();

        return [
            'name'          => $faker->words(2, true),
            'category'      => AssetCategories::DEFAULTS[array_rand(AssetCategories::DEFAULTS)][0],
            'manufacturer'  => $faker->company(),
            'model'         => $faker->bothify('Model-####'),
            'serial_number' => $faker->unique()->bothify('SN-########'),
            'status'        => 'in_stock',
            'location'      => $faker->city(),
            'assign_date'   => $faker->date(),
        ];
    }
}
