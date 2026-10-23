<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /** @use HasFactory<\Database\Factories\MaterialFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'material_group',
        'stock_unit',
        'quantity_on_hand',
        'reorder_level',
        'visual_key',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'string',
            'reorder_level' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderBy('created_at', 'desc');
    }

    public function productionConsumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        if ($this->reorder_level === null) {
            return false;
        }

        return bccomp($this->quantity_on_hand, $this->reorder_level, 6) <= 0;
    }
}
