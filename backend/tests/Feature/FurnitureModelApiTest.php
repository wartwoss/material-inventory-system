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
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
            'formula_key' => 'panel_area_m2',
            'formula_parameters' => ['waste_factor' => 1.15],
            'sort_order' => 1,
        ]);

        $oak = Material::factory()->create([
            'name' => 'Oak board',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Pine legs',
            'material_group' => 'wood',
            'purpose' => 'leg',
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
            'name' => 'Top',
            'material_group' => 'wood',
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
        ]);

        $compatible = Material::factory()->create([
            'name' => 'Oak board',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Wrong unit wood',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'm3',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Wrong purpose m2',
            'material_group' => 'wood',
            'purpose' => 'leg',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Wrong group m2',
            'material_group' => 'metal',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Inactive oak',
            'material_group' => 'wood',
            'purpose' => 'board',
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

    public function test_bedside_cabinet_returns_carcass_as_configurable_part(): void
    {
        $this->seed([
            \Database\Seeders\MaterialSeeder::class,
            \Database\Seeders\FurnitureModelSeeder::class,
            \Database\Seeders\ModelPartSeeder::class,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/bedside-cabinet');

        $response->assertOk();

        $parts = $response->json('parts');
        $carcass = collect($parts)->firstWhere('code', 'carcass');
        $door = collect($parts)->firstWhere('code', 'door');
        $feet = collect($parts)->firstWhere('code', 'feet');

        $this->assertNotNull($carcass);
        $this->assertTrue($carcass['is_configurable']);
        $this->assertSame('cabinet_carcass_m3', $carcass['formula_key']);
        $this->assertSame('m3', $carcass['consumption_unit']);
        $this->assertTrue($carcass['has_compatible_materials']);

        $this->assertNotNull($door);
        $this->assertTrue($door['is_configurable']);

        $this->assertNotNull($feet);
        $this->assertTrue($feet['is_configurable']);
    }

    public function test_bedside_cabinet_carcass_only_returns_wood_m3_materials(): void
    {
        $this->seed([
            \Database\Seeders\MaterialSeeder::class,
            \Database\Seeders\FurnitureModelSeeder::class,
            \Database\Seeders\ModelPartSeeder::class,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/bedside-cabinet');

        $response->assertOk();

        $carcass = collect($response->json('parts'))->firstWhere('code', 'carcass');

        foreach ($carcass['allowed_materials'] as $material) {
            $this->assertSame('wood', $material['material_group']);
            $this->assertSame('m3', $material['stock_unit']);
            $this->assertSame('board', $material['purpose']);
        }
    }

    public function test_bedside_cabinet_door_only_returns_board_materials(): void
    {
        $this->seed([
            \Database\Seeders\MaterialSeeder::class,
            \Database\Seeders\FurnitureModelSeeder::class,
            \Database\Seeders\ModelPartSeeder::class,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/bedside-cabinet');

        $response->assertOk();

        $door = collect($response->json('parts'))->firstWhere('code', 'door');

        $this->assertNotEmpty($door['allowed_materials']);

        foreach ($door['allowed_materials'] as $material) {
            $this->assertSame('board', $material['purpose']);
            $this->assertSame('m2', $material['stock_unit']);
        }
    }

    public function test_bedside_cabinet_legs_only_returns_leg_materials(): void
    {
        $this->seed([
            \Database\Seeders\MaterialSeeder::class,
            \Database\Seeders\FurnitureModelSeeder::class,
            \Database\Seeders\ModelPartSeeder::class,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/bedside-cabinet');

        $response->assertOk();

        // There is no separate legs part on the bedside cabinet; verify the table legs part.
        $table = $this->getJson('/api/v1/furniture-models/table')->json('parts');
        $legs = collect($table)->firstWhere('code', 'legs');

        $this->assertNotEmpty($legs['allowed_materials']);

        foreach ($legs['allowed_materials'] as $material) {
            $this->assertSame('leg', $material['purpose']);
            $this->assertSame('m3', $material['stock_unit']);
        }
    }

    public function test_bedside_cabinet_handle_only_returns_handle_materials(): void
    {
        $this->seed([
            \Database\Seeders\MaterialSeeder::class,
            \Database\Seeders\FurnitureModelSeeder::class,
            \Database\Seeders\ModelPartSeeder::class,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/bedside-cabinet');

        $response->assertOk();

        $handle = collect($response->json('parts'))->firstWhere('code', 'handle');

        $this->assertNotEmpty($handle['allowed_materials']);

        foreach ($handle['allowed_materials'] as $material) {
            $this->assertSame('handle', $material['purpose']);
            $this->assertSame('ea', $material['stock_unit']);
        }
    }

    public function test_bedside_cabinet_feet_only_returns_foot_materials(): void
    {
        $this->seed([
            \Database\Seeders\MaterialSeeder::class,
            \Database\Seeders\FurnitureModelSeeder::class,
            \Database\Seeders\ModelPartSeeder::class,
        ]);

        $response = $this->getJson('/api/v1/furniture-models/bedside-cabinet');

        $response->assertOk();

        $feet = collect($response->json('parts'))->firstWhere('code', 'feet');

        $this->assertNotEmpty($feet['allowed_materials']);

        foreach ($feet['allowed_materials'] as $material) {
            $this->assertSame('foot', $material['purpose']);
            $this->assertSame('m3', $material['stock_unit']);
        }
    }
}
