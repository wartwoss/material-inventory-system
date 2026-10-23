<?php

namespace Database\Factories;

use App\Models\FurnitureModel;
use App\Models\ModelPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelPart>
 */
class ModelPartFactory extends Factory
{
    protected $model = ModelPart::class;

    public function definition(): array
    {
        return [
            'furniture_model_id' => FurnitureModel::factory(),
            'code' => $this->faker->unique()->word(),
            'name' => $this->faker->words(2, true),
            'material_group' => $this->faker->randomElement(['wood', 'metal', 'ceramic', 'upholstery']),
            'consumption_unit' => $this->faker->randomElement(['m2', 'm3', 'm', 'ea', 'kg']),
            'formula_key' => 'panel_area_m2',
            'formula_parameters' => [],
            'is_configurable' => true,
            'sort_order' => 0,
        ];
    }
}
