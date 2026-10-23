<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FurnitureModel extends Model
{
    /** @use HasFactory<\Database\Factories\FurnitureModelFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'model_asset_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function modelParts(): HasMany
    {
        return $this->hasMany(ModelPart::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
