<?php

namespace App\Services;

use InvalidArgumentException;

class FormulaCalculator
{
    /**
     * Supported inventory units.
     */
    public const SUPPORTED_FORMULAS = [
        'panel_area_m2',
        'solid_volume_m3',
        'cabinet_carcass_m3',
        'fixed_each',
    ];

    /**
     * Calculate consumption for a model part using decimal-safe arithmetic.
     *
     * @param string $formulaKey
     * @param array<string, int> $dimensions length/width/height in mm
     * @param array<string, mixed> $parameters
     * @return string Consumption in the part's canonical unit, rounded up to 6 dp.
     */
    public function calculate(string $formulaKey, array $dimensions, array $parameters): string
    {
        return match ($formulaKey) {
            'panel_area_m2' => $this->panelAreaM2($dimensions, $parameters),
            'solid_volume_m3' => $this->solidVolumeM3($dimensions, $parameters),
            'cabinet_carcass_m3' => $this->cabinetCarcassM3($dimensions, $parameters),
            'fixed_each' => $this->fixedEach($parameters),
            default => throw new InvalidArgumentException("Unsupported formula key: {$formulaKey}"),
        };
    }

    /**
     * Panel area in m².
     *
     * Inputs:
     *   - dimension_axes: array of two axis keys from [length, width, height]
     *   - waste_factor: multiplier for offcuts/kerf
     */
    private function panelAreaM2(array $dimensions, array $parameters): string
    {
        $axes = $parameters['dimension_axes'] ?? [];

        if (count($axes) !== 2 || ! isset($dimensions[$axes[0]], $dimensions[$axes[1]])) {
            throw new InvalidArgumentException('panel_area_m2 requires two valid dimension axes.');
        }

        $areaMm2 = bcmul((string) $dimensions[$axes[0]], (string) $dimensions[$axes[1]], 0);
        $areaM2 = bcdiv($areaMm2, '1000000', 12);
        $wasteFactor = (string) ($parameters['waste_factor'] ?? 1);

        return $this->roundUp(bcmul($areaM2, $wasteFactor, 12), 6);
    }

    /**
     * Solid rectangular volume in m³.
     *
     * Inputs:
     *   - height_source: ['type' => 'dimension', 'axis' => 'height']
     *                    or ['type' => 'parameter', 'key' => 'foot_height_mm']
     *   - profile_width_mm, profile_thickness_mm
     *   - leg_count
     *   - waste_factor
     */
    private function solidVolumeM3(array $dimensions, array $parameters): string
    {
        $heightSource = $parameters['height_source'] ?? null;

        if (! is_array($heightSource)) {
            throw new InvalidArgumentException('solid_volume_m3 requires a height_source definition.');
        }

        if ($heightSource['type'] === 'dimension') {
            $axis = $heightSource['axis'];

            if (! isset($dimensions[$axis])) {
                throw new InvalidArgumentException("Dimension axis '{$axis}' not available.");
            }

            $heightMm = (string) $dimensions[$axis];
        } elseif ($heightSource['type'] === 'parameter') {
            $key = $heightSource['key'];

            if (! isset($parameters[$key])) {
                throw new InvalidArgumentException("Height parameter '{$key}' not provided.");
            }

            $heightMm = (string) $parameters[$key];
        } else {
            throw new InvalidArgumentException('height_source type must be dimension or parameter.');
        }

        $widthMm = (string) ($parameters['profile_width_mm'] ?? 0);
        $thicknessMm = (string) ($parameters['profile_thickness_mm'] ?? 0);
        $count = (string) ($parameters['leg_count'] ?? 1);
        $wasteFactor = (string) ($parameters['waste_factor'] ?? 1);

        $volumeMm3 = bcmul(bcmul(bcmul($heightMm, $widthMm, 0), $thicknessMm, 0), $count, 0);
        $volumeM3 = bcdiv($volumeMm3, '1000000000', 12);

        return $this->roundUp(bcmul($volumeM3, $wasteFactor, 12), 6);
    }

    /**
     * Simplified five-panel cabinet carcass volume in m³.
     *
     * Inputs:
     *   - length_mm, width_mm, height_mm (model dimensions)
     *   - panel_thickness_mm
     *   - waste_factor
     *
     * Assumptions:
     *   - Five-panel box: two sides (H×W), top/bottom (L×W), back (L×H).
     *   - No shelves, drawers, or internal divisions.
     *   - This is a material estimate, not a manufacturing cut list.
     */
    private function cabinetCarcassM3(array $dimensions, array $parameters): string
    {
        $lengthMm = (string) ($dimensions['length'] ?? 0);
        $widthMm = (string) ($dimensions['width'] ?? 0);
        $heightMm = (string) ($dimensions['height'] ?? 0);
        $thicknessMm = (string) ($parameters['panel_thickness_mm'] ?? 0);
        $wasteFactor = (string) ($parameters['waste_factor'] ?? 1);

        // 2 sides + 2 top/bottom panels + back
        $panelAreaMm2 = bcadd(
            bcadd(
                bcmul('2', bcmul($heightMm, $widthMm, 0), 0),
                bcmul('2', bcmul($lengthMm, $widthMm, 0), 0),
                0
            ),
            bcmul($lengthMm, $heightMm, 0),
            0
        );

        $volumeMm3 = bcmul($panelAreaMm2, $thicknessMm, 0);
        $volumeM3 = bcdiv($volumeMm3, '1000000000', 12);

        return $this->roundUp(bcmul($volumeM3, $wasteFactor, 12), 6);
    }

    /**
     * Fixed piece count.
     *
     * Inputs:
     *   - count_per_unit
     */
    private function fixedEach(array $parameters): string
    {
        $count = (string) ($parameters['count_per_unit'] ?? 0);

        return $this->roundUp($count, 6);
    }

    /**
     * Round a decimal string up to the given number of decimal places.
     */
    public function roundUp(string $value, int $scale): string
    {
        $factor = bcpow('10', (string) $scale, 0);
        $scaled = bcmul($value, $factor, $scale + 4);

        // Split into integer and fractional parts
        $dotPosition = strpos($scaled, '.');

        if ($dotPosition === false) {
            $integerPart = $scaled;
            $fractionalPart = '';
        } else {
            $integerPart = substr($scaled, 0, $dotPosition);
            $fractionalPart = substr($scaled, $dotPosition + 1);
        }

        // If there is any positive fractional remainder, add one to the integer part.
        if (rtrim($fractionalPart, '0') !== '') {
            $integerPart = bcadd($integerPart, '1', 0);
        }

        return bcdiv($integerPart, $factor, $scale);
    }
}
