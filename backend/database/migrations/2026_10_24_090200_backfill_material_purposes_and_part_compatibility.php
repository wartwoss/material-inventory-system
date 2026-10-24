<?php

use App\Models\FurnitureModel;
use App\Models\Material;
use App\Models\ModelPart;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill material purposes and furniture-part compatibility.
     *
     * Existing local materials are left alone unless they match a known demo SKU.
     * Demo records are updated to canonical purposes; all other rows keep their
     * existing value or are left nullable.
     */
    public function up(): void
    {
        // Demo material purposes by stable SKU.
        $purposeBySku = [
            'OAK-020' => Material::PURPOSE_BOARD,
            'WAL-020' => Material::PURPOSE_BOARD,
            'PIN-LEG-50' => Material::PURPOSE_LEG,
            'BIR-040' => Material::PURPOSE_BOARD,
            'PIN-FOOT' => Material::PURPOSE_FOOT,
            'BRA-HDL' => Material::PURPOSE_HANDLE,
            'CER-WHT' => Material::PURPOSE_GENERAL,
            'UPH-GRY' => Material::PURPOSE_GENERAL,
            'STL-BRK' => Material::PURPOSE_GENERAL,
            'GLU-WD' => Material::PURPOSE_GENERAL,
        ];

        foreach ($purposeBySku as $sku => $purpose) {
            DB::table('materials')
                ->where('sku', $sku)
                ->update(['purpose' => $purpose]);
        }

        // Backfill demo model-part compatibility by canonical part code.
        $compatibilityByCode = [
            'carcass' => [Material::PURPOSE_BOARD],
            'door' => [Material::PURPOSE_BOARD],
            'top' => [Material::PURPOSE_BOARD],
            'shelf' => [Material::PURPOSE_BOARD],
            'legs' => [Material::PURPOSE_LEG],
            'handle' => [Material::PURPOSE_HANDLE],
            'feet' => [Material::PURPOSE_FOOT],
        ];

        foreach ($compatibilityByCode as $code => $purposes) {
            DB::table('model_parts')
                ->where('code', $code)
                ->update(['compatible_purposes' => json_encode($purposes)]);
        }

        // Any model part without a compatibility list gets [general] so existing
        // demo data keeps working after the new filter is enabled.
        DB::table('model_parts')
            ->whereNull('compatible_purposes')
            ->update(['compatible_purposes' => json_encode([Material::PURPOSE_GENERAL])]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('materials')->update(['purpose' => null]);
        DB::table('model_parts')->update(['compatible_purposes' => null]);
    }
};
