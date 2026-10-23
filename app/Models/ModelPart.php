<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelPart extends Model
{
    /** @use HasFactory<\Database\Factories\ModelPartFactory> */
    use HasFactory;

    protected $fillable = [
        'furniture_model_id',
        'code',
        'name',
        'material_group',
        'consumption_unit',
        'formula_key',
        'formula_parameters',
    ];

    protected function casts(): array
    {
        return [
            'formula_parameters' => 'array',
        ];
    }

    public function furnitureModel(): BelongsTo
    {
        return $this->belongsTo(FurnitureModel::class);
    }

    public function productionConsumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }
}
