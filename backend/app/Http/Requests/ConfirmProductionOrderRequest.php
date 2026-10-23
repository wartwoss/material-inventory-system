<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmProductionOrderRequest extends FormRequest
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
            'furniture_model_slug' => ['required', 'string', 'exists:furniture_models,slug'],
            'length_mm' => ['required', 'integer', 'min:1'],
            'width_mm' => ['required', 'integer', 'min:1'],
            'height_mm' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.code' => ['required', 'string'],
            'parts.*.material_id' => ['required', 'integer', 'exists:materials,id'],
        ];
    }
}
