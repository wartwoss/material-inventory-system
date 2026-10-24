<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Services\StockAdjustmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:materials,sku'],
            'material_group' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:50', Rule::in(Material::PURPOSES)],
            'stock_unit' => ['required', 'string', Rule::in(StockAdjustmentService::SUPPORTED_UNITS)],
            'opening_quantity' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'visual_key' => ['nullable', 'string', 'max:255'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'texture' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
