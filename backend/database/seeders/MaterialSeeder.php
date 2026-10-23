<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /**
     * Seed realistic starter materials for local development and demos.
     */
    public function run(): void
    {
        $materials = [
            [
                'name' => 'Oak board 20 mm',
                'sku' => 'OAK-020',
                'material_group' => 'wood',
                'stock_unit' => 'm2',
                'quantity_on_hand' => '12.500000',
                'reorder_level' => '10.000000',
                'visual_key' => 'oak-natural',
                'color_hex' => '#c49a6c',
                'is_active' => true,
            ],
            [
                'name' => 'Walnut board 20 mm',
                'sku' => 'WAL-020',
                'material_group' => 'wood',
                'stock_unit' => 'm2',
                'quantity_on_hand' => '8.000000',
                'reorder_level' => '5.000000',
                'visual_key' => 'walnut',
                'color_hex' => '#5c4033',
                'is_active' => true,
            ],
            [
                'name' => 'Pine legs 50×50 mm',
                'sku' => 'PIN-LEG-50',
                'material_group' => 'wood',
                'stock_unit' => 'm3',
                'quantity_on_hand' => '0.250000',
                'reorder_level' => '0.300000',
                'visual_key' => 'pine',
                'color_hex' => '#e8d4a8',
                'is_active' => true,
            ],
            [
                'name' => 'Brass handle',
                'sku' => 'BRA-HDL',
                'material_group' => 'metal',
                'stock_unit' => 'ea',
                'quantity_on_hand' => '24.000000',
                'reorder_level' => '20.000000',
                'visual_key' => 'brass',
                'color_hex' => '#b5a642',
                'is_active' => true,
            ],
            [
                'name' => 'Ceramic tile white',
                'sku' => 'CER-WHT',
                'material_group' => 'ceramic',
                'stock_unit' => 'm2',
                'quantity_on_hand' => '3.200000',
                'reorder_level' => '4.000000',
                'visual_key' => 'ceramic-white',
                'color_hex' => '#f5f5f5',
                'is_active' => true,
            ],
            [
                'name' => 'Cotton upholstery grey',
                'sku' => 'UPH-GRY',
                'material_group' => 'upholstery',
                'stock_unit' => 'm2',
                'quantity_on_hand' => '15.000000',
                'reorder_level' => '6.000000',
                'visual_key' => 'fabric-grey',
                'color_hex' => '#7a7a7a',
                'is_active' => true,
            ],
            [
                'name' => 'Steel bracket',
                'sku' => 'STL-BRK',
                'material_group' => 'metal',
                'stock_unit' => 'ea',
                'quantity_on_hand' => '0.000000',
                'reorder_level' => '10.000000',
                'visual_key' => 'steel',
                'color_hex' => '#9ca3af',
                'is_active' => true,
            ],
            [
                'name' => 'Wood glue',
                'sku' => 'GLU-WD',
                'material_group' => 'finish',
                'stock_unit' => 'kg',
                'quantity_on_hand' => '4.750000',
                'reorder_level' => '2.000000',
                'visual_key' => 'glue',
                'color_hex' => '#ffffff',
                'is_active' => true,
            ],
        ];

        foreach ($materials as $material) {
            Material::factory()->create($material);
        }
    }
}
