<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FurnitureModel;
use App\Models\Material;
use Illuminate\Http\JsonResponse;

class FurnitureModelController extends Controller
{
    public function index(): JsonResponse
    {
        $models = FurnitureModel::active()
            ->orderBy('name')
            ->get([
                'id',
                'slug',
                'name',
                'model_asset_path',
                'default_length_mm',
                'min_length_mm',
                'max_length_mm',
                'default_width_mm',
                'min_width_mm',
                'max_width_mm',
                'default_height_mm',
                'min_height_mm',
                'max_height_mm',
                'is_active',
            ]);

        return response()->json($models);
    }

    public function show(string $slug): JsonResponse
    {
        $model = FurnitureModel::active()
            ->where('slug', $slug)
            ->with(['modelParts' => function ($query) {
                $query->where('is_configurable', true)
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->firstOrFail();

        $parts = $model->modelParts->map(function ($part) {
            $compatiblePurposes = $part->compatible_purposes ?? [\App\Models\Material::PURPOSE_GENERAL];

            $materials = Material::active()
                ->where('material_group', $part->material_group)
                ->where('stock_unit', $part->consumption_unit)
                ->where(function ($query) use ($compatiblePurposes) {
                    $query->whereNull('purpose')
                        ->orWhereIn('purpose', $compatiblePurposes);
                })
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'sku',
                    'material_group',
                    'purpose',
                    'stock_unit',
                    'quantity_on_hand',
                    'visual_key',
                    'color_hex',
                    'texture_path',
                    'is_active',
                ]);

            return [
                'id' => $part->id,
                'code' => $part->code,
                'name' => $part->name,
                'material_group' => $part->material_group,
                'compatible_purposes' => $compatiblePurposes,
                'consumption_unit' => $part->consumption_unit,
                'formula_key' => $part->formula_key,
                'formula_parameters' => $part->formula_parameters,
                'is_configurable' => $part->is_configurable,
                'sort_order' => $part->sort_order,
                'allowed_materials' => $materials,
                'has_compatible_materials' => $materials->isNotEmpty(),
            ];
        });

        return response()->json([
            'id' => $model->id,
            'slug' => $model->slug,
            'name' => $model->name,
            'model_asset_path' => $model->model_asset_path,
            'dimensions' => [
                'length' => [
                    'default' => $model->default_length_mm,
                    'min' => $model->min_length_mm,
                    'max' => $model->max_length_mm,
                    'unit' => 'mm',
                ],
                'width' => [
                    'default' => $model->default_width_mm,
                    'min' => $model->min_width_mm,
                    'max' => $model->max_width_mm,
                    'unit' => 'mm',
                ],
                'height' => [
                    'default' => $model->default_height_mm,
                    'min' => $model->min_height_mm,
                    'max' => $model->max_height_mm,
                    'unit' => 'mm',
                ],
            ],
            'parts' => $parts,
        ]);
    }
}
