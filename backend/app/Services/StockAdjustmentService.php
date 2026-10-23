<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StockAdjustmentService
{
    /**
     * Supported inventory units.
     */
    public const SUPPORTED_UNITS = ['m2', 'm3', 'm', 'ea', 'kg'];

    /**
     * Create a material and optionally record opening stock.
     *
     * @param array<string, mixed> $data
     * @return Material
     */
    public function createMaterial(array $data): Material
    {
        return DB::transaction(function () use ($data) {
            $openingQuantity = $data['opening_quantity'] ?? '0';
            unset($data['opening_quantity']);

            $data['quantity_on_hand'] = $openingQuantity;

            $material = Material::create($data);

            if (bccomp($openingQuantity, '0', 6) !== 0) {
                $this->recordMovement(
                    $material,
                    InventoryMovement::TYPE_OPENING,
                    $openingQuantity,
                    $openingQuantity,
                    'Opening stock'
                );
            }

            return $material;
        });
    }

    /**
     * Adjust a material's stock by a signed quantity.
     *
     * @param Material $material
     * @param string $quantity Positive amount to add; negative amount to remove.
     * @param string $reason
     * @return Material
     */
    public function adjustStock(Material $material, string $quantity, string $reason): Material
    {
        if (bccomp($quantity, '0', 6) === 0) {
            throw new InvalidArgumentException('Adjustment quantity must not be zero.');
        }

        return DB::transaction(function () use ($material, $quantity, $reason) {
            $locked = Material::lockForUpdate()->findOrFail($material->id);

            $currentBalance = $locked->quantity_on_hand;
            $newBalance = bcadd($currentBalance, $quantity, 6);

            if (bccomp($newBalance, '0', 6) < 0) {
                throw new RuntimeException('Insufficient stock for adjustment.');
            }

            $locked->quantity_on_hand = $newBalance;
            $locked->save();

            $this->recordMovement($locked, InventoryMovement::TYPE_MANUAL_ADJUSTMENT, $quantity, $newBalance, $reason);

            return $locked;
        });
    }

    /**
     * Record an inventory movement row.
     *
     * @param Material $material
     * @param string $movementType
     * @param string $quantityDelta
     * @param string $balanceAfter
     * @param string|null $reason
     * @param int|null $productionOrderId
     * @return InventoryMovement
     */
    public function recordMovement(
        Material $material,
        string $movementType,
        string $quantityDelta,
        string $balanceAfter,
        ?string $reason = null,
        ?int $productionOrderId = null
    ): InventoryMovement {
        return $material->inventoryMovements()->create([
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'balance_after' => $balanceAfter,
            'reason' => $reason,
            'production_order_id' => $productionOrderId,
        ]);
    }

    /**
     * Validate a unit string.
     */
    public static function isValidUnit(string $unit): bool
    {
        return in_array($unit, self::SUPPORTED_UNITS, true);
    }
}
