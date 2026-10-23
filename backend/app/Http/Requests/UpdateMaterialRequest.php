<?php

namespace App\Http\Requests;

use App\Services\StockAdjustmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $materialId = $this->route('material');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('materials', 'sku')->ignore($materialId)],
            'material_group' => ['sometimes', 'required', 'string', 'max:255'],
            'stock_unit' => ['sometimes', 'required', 'string', Rule::in(StockAdjustmentService::SUPPORTED_UNITS)],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'visual_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
