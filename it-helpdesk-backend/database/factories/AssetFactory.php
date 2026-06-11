<?php

namespace Database\Factories;

use App\Support\AssetCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(2, true),
            'category'      => AssetCategories::DEFAULTS[array_rand(AssetCategories::DEFAULTS)][0],
            'manufacturer'  => $this->faker->company(),
            'model'         => $this->faker->bothify('Model-####'),
            'serial_number' => $this->faker->unique()->bothify('SN-########'),
            'status'        => 'in_stock',
            'location'      => $this->faker->city(),
            'assign_date'   => $this->faker->date(),
        ];
    }
}
