<?php

namespace Database\Seeders;

use App\Models\FurnitureModel;
use Illuminate\Database\Seeder;

class FurnitureModelSeeder extends Seeder
{
    /**
     * Seed the configurable furniture models for the MVP.
     */
    public function run(): void
    {
        $models = [
            [
                'slug' => 'table',
                'name' => 'Dining table',
                'model_asset_path' => null,
                'is_active' => true,
                'default_length_mm' => 1200,
                'min_length_mm' => 800,
                'max_length_mm' => 2400,
                'default_width_mm' => 700,
                'min_width_mm' => 500,
                'max_width_mm' => 1200,
                'default_height_mm' => 740,
                'min_height_mm' => 680,
                'max_height_mm' => 800,
            ],
            [
                'slug' => 'bedside-cabinet',
                'name' => 'Bedside cabinet',
                'model_asset_path' => null,
                'is_active' => true,
                'default_length_mm' => 450,
                'min_length_mm' => 350,
                'max_length_mm' => 600,
                'default_width_mm' => 400,
                'min_width_mm' => 300,
                'max_width_mm' => 500,
                'default_height_mm' => 650,
                'min_height_mm' => 500,
                'max_height_mm' => 900,
            ],
        ];

        foreach ($models as $attributes) {
            FurnitureModel::updateOrCreate(
                ['slug' => $attributes['slug']],
                $attributes
            );
        }
    }
}
