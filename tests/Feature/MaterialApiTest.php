<?php

namespace Tests\Feature;

use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MaterialApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_can_list_materials(): void
    {
        Material::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/materials');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_material_with_opening_stock(): void
    {
        $payload = [
            'name' => 'Oak Wood',
            'sku' => 'OAK-001',
            'material_group' => 'wood',
            'stock_unit' => 'm3',
            'opening_quantity' => 12.5,
            'reorder_level' => 5.0,
        ];

        $response = $this->postJson('/api/v1/materials', $payload);

        $response->assertCreated()
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 12.5)
            ->assertJsonPath('inventory_movements.0.movement_type', 'opening')
            ->assertJsonPath('inventory_movements.0.quantity_delta', fn ($value) => $value == 12.5);

        $this->assertDatabaseHas('materials', [
            'name' => 'Oak Wood',
            'sku' => 'OAK-001',
            'quantity_on_hand' => '12.500000',
        ]);
    }

    public function test_creating_material_rejects_invalid_unit(): void
    {
        $payload = [
            'name' => 'Invalid Unit Material',
            'material_group' => 'wood',
            'stock_unit' => 'litres',
        ];

        $response = $this->postJson('/api/v1/materials', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_unit']);
    }

    public function test_creating_material_rejects_negative_opening_quantity(): void
    {
        $payload = [
            'name' => 'Bad Material',
            'material_group' => 'wood',
            'stock_unit' => 'm3',
            'opening_quantity' => -5,
        ];

        $response = $this->postJson('/api/v1/materials', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_quantity']);
    }

    public function test_can_view_single_material(): void
    {
        $material = Material::factory()->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}");

        $response->assertOk()
            ->assertJsonPath('id', $material->id);
    }

    public function test_can_update_material_details_without_changing_stock(): void
    {
        $material = Material::factory()->create([
            'quantity_on_hand' => '100.000000',
        ]);

        $response = $this->putJson("/api/v1/materials/{$material->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 100.0);

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'name' => 'Updated Name',
            'quantity_on_hand' => '100.000000',
        ]);
    }

    public function test_cannot_change_stock_unit_after_movements_exist(): void
    {
        $material = Material::factory()->create(['stock_unit' => 'm3']);
        $material->inventoryMovements()->create([
            'movement_type' => 'opening',
            'quantity_delta' => '10.000000',
            'balance_after' => '10.000000',
            'reason' => 'Opening',
        ]);

        $response = $this->putJson("/api/v1/materials/{$material->id}", [
            'stock_unit' => 'm2',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot change stock unit after transactions exist.');
    }

    public function test_can_add_stock(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '10.000000']);

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => 5,
            'reason' => 'Received shipment',
        ]);

        $response->assertOk()
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 15.0)
            ->assertJsonPath('inventory_movements.0.quantity_delta', fn ($value) => $value == 5.0)
            ->assertJsonPath('inventory_movements.0.balance_after', fn ($value) => $value == 15.0);
    }

    public function test_can_remove_stock(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '10.000000']);

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => -4,
            'reason' => 'Used for sample',
        ]);

        $response->assertOk()
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 6.0)
            ->assertJsonPath('inventory_movements.0.quantity_delta', fn ($value) => $value == -4.0)
            ->assertJsonPath('inventory_movements.0.balance_after', fn ($value) => $value == 6.0);
    }

    public function test_cannot_remove_more_stock_than_available(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '2.000000']);

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => -5,
            'reason' => 'Attempt overdraw',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient stock for adjustment.')
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 2.0);

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'quantity_on_hand' => '2.000000',
        ]);
    }

    public function test_adjustment_requires_reason(): void
    {
        $material = Material::factory()->create();

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_can_view_material_movement_history(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '5.000000']);
        $material->inventoryMovements()->create([
            'movement_type' => 'manual_adjustment',
            'quantity_delta' => '5.000000',
            'balance_after' => '5.000000',
            'reason' => 'Initial top-up',
        ]);

        $response = $this->getJson("/api/v1/materials/{$material->id}/movements");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.movement_type', 'manual_adjustment');
    }

    public function test_movement_history_is_immutable(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '5.000000']);
        $movement = $material->inventoryMovements()->create([
            'movement_type' => 'manual_adjustment',
            'quantity_delta' => '5.000000',
            'balance_after' => '5.000000',
            'reason' => 'Initial top-up',
        ]);

        // No update/delete routes exist, so HTTP methods other than GET should 404.
        $this->putJson("/api/v1/materials/{$material->id}/movements/{$movement->id}", [
            'quantity_delta' => 999,
        ])->assertNotFound();

        $this->deleteJson("/api/v1/materials/{$material->id}/movements/{$movement->id}")
            ->assertNotFound();
    }
}
