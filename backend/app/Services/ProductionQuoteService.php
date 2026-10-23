<?php

namespace App\Services;

use App\Models\FurnitureModel;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProductionQuoteService
{
    public function __construct(private readonly FormulaCalculator $formulaCalculator)
    {
    }

    /**
     * Calculate a production quote without changing inventory.
     *
     * Expected input keys:
     * - furniture_model_slug: string
     * - length_mm, width_mm, height_mm: positive integers
     * - quantity: positive integer
     * - parts: array of ['code' => string, 'material_id' => int]
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        $model = FurnitureModel::active()
            ->where('slug', $input['furniture_model_slug'])
            ->with(['modelParts' => function ($query) {
                $query->where('is_configurable', true)->orderBy('sort_order');
            }])
            ->first();

        if (! $model) {
            throw new InvalidArgumentException('Furniture model not found or inactive.');
        }

        $dimensions = [
            'length' => (int) $input['length_mm'],
            'width' => (int) $input['width_mm'],
            'height' => (int) $input['height_mm'],
        ];

        $this->validateDimensions($model, $dimensions);

        $quantity = (int) $input['quantity'];

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        $submittedParts = collect($input['parts'] ?? [])->keyBy('code');
        $configurableParts = $model->modelParts;

        $this->validatePartSelections($configurableParts, $submittedParts);

        $components = [];
        $materialAggregates = [];

        foreach ($configurableParts as $part) {
            $selection = $submittedParts[$part->code];
            $material = Material::active()->find($selection['material_id']);

            if (! $material) {
                throw new InvalidArgumentException("Selected material for part '{$part->code}' is not available.");
            }

            if ($material->material_group !== $part->material_group) {
                throw new InvalidArgumentException(
                    "Material group mismatch for part '{$part->code}'."
                );
            }

            if ($material->stock_unit !== $part->consumption_unit) {
                throw new InvalidArgumentException(
                    "Unit mismatch for part '{$part->code}': expected {$part->consumption_unit}, got {$material->stock_unit}."
                );
            }

            $perUnit = $this->formulaCalculator->calculate(
                $part->formula_key,
                $dimensions,
                $part->formula_parameters ?? []
            );

            $total = bcmul($perUnit, (string) $quantity, 6);

            $components[] = [
                'part_id' => $part->id,
                'part_code' => $part->code,
                'part_name' => $part->name,
                'material_id' => $material->id,
                'material_name' => $material->name,
                'unit' => $part->consumption_unit,
                'per_unit_quantity' => $perUnit,
                'total_quantity' => $total,
            ];

            $materialId = $material->id;

            if (! isset($materialAggregates[$materialId])) {
                $materialAggregates[$materialId] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'unit' => $material->stock_unit,
                    'required_quantity' => '0.000000',
                    'available_quantity' => $material->quantity_on_hand,
                ];
            }

            $materialAggregates[$materialId]['required_quantity'] = bcadd(
                $materialAggregates[$materialId]['required_quantity'],
                $total,
                6
            );
        }

        $materials = [];
        $isSufficient = true;

        foreach ($materialAggregates as $aggregate) {
            $aggregate['is_sufficient'] = bccomp(
                $aggregate['available_quantity'],
                $aggregate['required_quantity'],
                6
            ) >= 0;

            if (! $aggregate['is_sufficient']) {
                $isSufficient = false;
            }

            $materials[] = $aggregate;
        }

        return [
            'furniture_model' => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
            ],
            'configuration' => [
                'length_mm' => $dimensions['length'],
                'width_mm' => $dimensions['width'],
                'height_mm' => $dimensions['height'],
                'quantity' => $quantity,
            ],
            'components' => $components,
            'materials' => $materials,
            'is_sufficient' => $isSufficient,
        ];
    }

    /**
     * @param FurnitureModel $model
     * @param array<string, int> $dimensions
     */
    private function validateDimensions(FurnitureModel $model, array $dimensions): void
    {
        $constraints = [
            'length' => ['min' => $model->min_length_mm, 'max' => $model->max_length_mm],
            'width' => ['min' => $model->min_width_mm, 'max' => $model->max_width_mm],
            'height' => ['min' => $model->min_height_mm, 'max' => $model->max_height_mm],
        ];

        foreach ($constraints as $axis => $constraint) {
            $value = $dimensions[$axis];

            if ($value < $constraint['min'] || $value > $constraint['max']) {
                throw new InvalidArgumentException(
                    "Dimension '{$axis}' must be between {$constraint['min']} and {$constraint['max']} mm."
                );
            }
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $configurableParts
     * @param \Illuminate\Support\Collection $submittedParts
     */
    private function validatePartSelections($configurableParts, $submittedParts): void
    {
        $validCodes = $configurableParts->pluck('code')->flip();

        foreach ($submittedParts->keys() as $code) {
            if (! $validCodes->has($code)) {
                throw new InvalidArgumentException("Unknown or non-configurable part code: {$code}");
            }
        }

        foreach ($configurableParts as $part) {
            if (! $submittedParts->has($part->code)) {
                throw new InvalidArgumentException("Missing material selection for part '{$part->code}'.");
            }

            $selection = $submittedParts[$part->code];

            if (! isset($selection['material_id']) || ! is_numeric($selection['material_id'])) {
                throw new InvalidArgumentException("Invalid material selection for part '{$part->code}'.");
            }
        }
    }
}
