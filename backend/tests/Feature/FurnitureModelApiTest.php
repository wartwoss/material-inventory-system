<?php

namespace Tests\Feature;

use App\Models\FurnitureModel;
use App\Models\Material;
use App\Models\ModelPart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureModelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_furniture_models(): void
    {
        FurnitureModel::factory()->create([
            'slug' => 'table',
            'name' => 'Table',
            'is_active' => true,
            'default_length_mm' => 1200,
            'min_length_mm' => 800,
            'max_length_mm' => 2400,
        ]);

        FurnitureModel::factory()->create([
            'slug' => 'retired',
            'name' => 'Retired model',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/furniture-models');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.slug', 'table')
            ->assertJsonPath('0.name', 'Table')
            ->assertJsonPath('0.min_length_mm', 800)
            ->assertJsonPath('0.max_length_mm', 2400);
    }

    public function test_show_returns_model_with_parts_and_compatible_materials(): void
    {
        $model = FurnitureModel::factory()->create([
            'slug' => 'table',
            'is_active' => true,
            'default_length_mm' => 1200,
            'min_length_mm' => 800,
            'max_length_mm' => 2400,
        ]);

        ModelPart::factory()->create([
            'furniture_model_id' => $model->id,
            'code' => 'top',
            'name' => 'Tabletop',
            'material_group' => 'wood',
            'consumption_unit' => 'm2',
            'formula_key' => 'panel_area_m2',
            'formula_parameters' => ['waste_factor' => 1.15],
            'sort_order' => 1,
        ]);

        $oak = Material::factory()->create([
            'name' => 'Oak board',
            'material_group' => 'wood',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Pine legs',
            'material_group' => 'wood',
            'stock_unit' => 'm3',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/table');

        $response->assertOk()
            ->assertJsonPath('slug', 'table')
            ->assertJsonPath('dimensions.length.min', 800)
            ->assertJsonPath('parts.0.code', 'top')
            ->assertJsonPath('parts.0.consumption_unit', 'm2')
            ->assertJsonPath('parts.0.allowed_materials.0.id', $oak->id)
            ->assertJsonPath('parts.0.has_compatible_materials', true);
    }

    public function test_show_excludes_incompatible_materials(): void
    {
        $model = FurnitureModel::factory()->create(['slug' => 'table', 'is_active' => true]);

        ModelPart::factory()->create([
            'furniture_model_id' => $model->id,
            'code' => 'top',
            'material_group' => 'wood',
            'consumption_unit' => 'm2',
        ]);

        $compatible = Material::factory()->create([
            'name' => 'Oak board',
            'material_group' => 'wood',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Wrong unit wood',
            'material_group' => 'wood',
            'stock_unit' => 'm3',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Wrong group m2',
            'material_group' => 'metal',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Inactive oak',
            'material_group' => 'wood',
            'stock_unit' => 'm2',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/table');

        $response->assertOk();

        $materials = $response->json('parts.0.allowed_materials');

        $this->assertCount(1, $materials);
        $this->assertSame($compatible->id, $materials[0]['id']);
    }

    public function test_show_returns_404_for_inactive_model(): void
    {
        FurnitureModel::factory()->create([
            'slug' => 'retired',
            'name' => 'Retired',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/furniture-models/retired')->assertNotFound();
    }
}
