<?php

namespace Database\Seeders;

use App\Models\FurnitureModel;
use App\Models\ModelPart;
use Illuminate\Database\Seeder;

class ModelPartSeeder extends Seeder
{
    /**
     * Seed the configurable parts for each furniture model.
     *
     * Compatibility is resolved at runtime by matching the part's
     * material_group and consumption_unit against active materials.
     */
    public function run(): void
    {
        $definitions = [
            'table' => [
                [
                    'code' => 'top',
                    'name' => 'Tabletop',
                    'material_group' => 'wood',
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'waste_factor' => 1.15,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'legs',
                    'name' => 'Legs',
                    'material_group' => 'wood',
                    'consumption_unit' => 'm3',
                    'formula_key' => 'solid_volume_m3',
                    'formula_parameters' => [
                        'height_source' => ['type' => 'dimension', 'axis' => 'height'],
                        'profile_width_mm' => 50,
                        'profile_thickness_mm' => 50,
                        'leg_count' => 4,
                        'waste_factor' => 1.2,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
            ],
            'bedside-cabinet' => [
                [
                    'code' => 'carcass',
                    'name' => 'Carcass',
                    'material_group' => 'wood',
                    'consumption_unit' => 'm3',
                    'formula_key' => 'cabinet_carcass_m3',
                    'formula_parameters' => [
                        'panel_thickness_mm' => 18,
                        'waste_factor' => 1.25,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'door',
                    'name' => 'Door / drawer front',
                    'material_group' => 'wood',
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'height'],
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'handle',
                    'name' => 'Handle',
                    'material_group' => 'metal',
                    'consumption_unit' => 'ea',
                    'formula_key' => 'fixed_each',
                    'formula_parameters' => [
                        'count_per_unit' => 1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 3,
                ],
                [
                    'code' => 'feet',
                    'name' => 'Feet',
                    'material_group' => 'wood',
                    'consumption_unit' => 'm3',
                    'formula_key' => 'solid_volume_m3',
                    'formula_parameters' => [
                        'height_source' => ['type' => 'parameter', 'key' => 'foot_height_mm'],
                        'foot_height_mm' => 60,
                        'profile_width_mm' => 40,
                        'profile_thickness_mm' => 40,
                        'leg_count' => 4,
                        'waste_factor' => 1.15,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 4,
                ],
            ],
        ];

        foreach ($definitions as $slug => $parts) {
            $model = FurnitureModel::where('slug', $slug)->first();

            if (! $model) {
                continue;
            }

            foreach ($parts as $attributes) {
                ModelPart::updateOrCreate(
                    [
                        'furniture_model_id' => $model->id,
                        'code' => $attributes['code'],
                    ],
                    $attributes
                );
            }
        }
    }
}
