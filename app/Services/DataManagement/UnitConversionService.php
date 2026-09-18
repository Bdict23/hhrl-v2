<?php

namespace App\Services\DataManagement;

use App\Models\DataManagement\Item;
use App\Models\DataManagement\Recipe;
use App\Models\DataManagement\UnitOfMeasure;
use App\Models\DataManagement\UnitConvertion;
use App\Models\DataManagement\Price;
use App\Models\Settings\SystemParameter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UnitConversionService
{
    /**
     * Standard conversion ratios to canonical base unit for each measurement type:
     * - WEIGHT canonical: 'g' (gram)
     * - VOLUME canonical: 'ml' (milliliter)
     * - UNIT canonical:   'pc' (piece)
     * - LENGTH canonical: 'm' (meter)
     */
    protected const CANONICAL_FACTORS = [
        'WEIGHT' => [
            'kg'        => 1000.0,
            'kg.'       => 1000.0,
            'kl'        => 1000.0,
            'kls'       => 1000.0,
            'kls.'      => 1000.0,
            'kilo'      => 1000.0,
            'kilogram'  => 1000.0,
            'kilograms' => 1000.0,
            'g'         => 1.0,
            'gram'      => 1.0,
            'grams'     => 1.0,
            'mg'        => 0.001,
            'mg.'       => 0.001,
            'milligram' => 0.001,
            'milligrams'=> 0.001,
            'oz'        => 28.349523,
            'ounce'     => 28.349523,
            'lb'        => 453.59237,
            'lbs'       => 453.59237,
            'pound'     => 453.59237,
        ],
        'VOLUME' => [
            'l'          => 1000.0,
            'ltr'        => 1000.0,
            'liter'      => 1000.0,
            'liters'     => 1000.0,
            'ml'         => 1.0,
            'ml.'        => 1.0,
            'milliliter' => 1.0,
            'milliliters'=> 1.0,
            'gal'        => 3785.411784,
            'gal.'       => 3785.411784,
            'gallon'     => 3785.411784,
            'gallons'    => 3785.411784,
            'tbs'        => 14.786765,
            'tbs.'       => 14.786765,
            'tbsp'       => 14.786765,
            'tablespoon' => 14.786765,
            'tsp'        => 4.928922,
            'tsp.'       => 4.928922,
            'teaspoon'   => 4.928922,
            'cup'        => 236.588237,
            'cups'       => 236.588237,
            'dl'         => 100.0,
            'deciliter'  => 100.0,
            'fl oz'      => 29.57353,
            'floz'       => 29.57353,
        ],
        'UNIT' => [
            'pc'         => 1.0,
            'pc.'        => 1.0,
            'piece'      => 1.0,
            'pieces'     => 1.0,
            'unit'       => 1.0,
            'units'      => 1.0,
            'doz'        => 12.0,
            'doz.'       => 12.0,
            'dozen'      => 12.0,
            '½ doz.'     => 6.0,
            '½ doz'      => 6.0,
            'half doz'   => 6.0,
            'half dozen' => 6.0,
            'pair'       => 2.0,
            'set'        => 1.0,
            'tray'       => 24.0,
            'trays'      => 24.0,
        ],
        'LENGTH' => [
            'm'          => 1.0,
            'meter'      => 1.0,
            'meters'     => 1.0,
            'cm'         => 0.01,
            'centimeter' => 0.01,
            'mm'         => 0.001,
            'millimeter' => 0.001,
            'in'         => 0.0254,
            'in.'        => 0.0254,
            'inch'       => 0.0254,
            'inches'     => 0.0254,
            'ft'         => 0.3048,
            'ft.'        => 0.3048,
            'foot'       => 0.3048,
            'feet'       => 0.3048,
        ],
    ];

    /**
     * Standardized 1:1 conversion ratios relative to the category normalized base unit:
     * - WEIGHT Base Unit: 'kg'
     * - VOLUME Base Unit: 'L'
     * - UNIT   Base Unit: 'pc'
     * - LENGTH Base Unit: 'm'
     */
    public const STANDARD_CONVERSIONS = [
        'WEIGHT' => [
            'base_unit' => 'kg',
            'units' => [
                'g'   => ['name' => 'Grams',       'symbol' => 'g',   'label' => 'Grams (g)',       'ratio' => 0.001],
                'kg'  => ['name' => 'Kilograms',   'symbol' => 'kg',  'label' => 'Kilograms (kg)',  'ratio' => 1.0],
                'mg'  => ['name' => 'Milligrams',  'symbol' => 'mg',  'label' => 'Milligrams (mg)', 'ratio' => 0.000001],
                'lbs' => ['name' => 'Pounds',      'symbol' => 'lbs', 'label' => 'Pounds (lbs)',   'ratio' => 0.45359237],
                'oz'  => ['name' => 'Ounces',      'symbol' => 'oz',  'label' => 'Ounces (oz)',     'ratio' => 0.02834952],
            ],
        ],
        'VOLUME' => [
            'base_unit' => 'L',
            'units' => [
                'ml'   => ['name' => 'Milliliters', 'symbol' => 'mL',   'label' => 'Milliliters (mL)', 'ratio' => 0.001],
                'l'    => ['name' => 'Liters',      'symbol' => 'L',    'label' => 'Liters (L)',       'ratio' => 1.0],
                'tbsp' => ['name' => 'Tablespoon',  'symbol' => 'tbsp', 'label' => 'Tablespoon (tbsp)', 'ratio' => 0.0147868],
                'tsp'  => ['name' => 'Teaspoon',    'symbol' => 'tsp',  'label' => 'Teaspoon (tsp)',    'ratio' => 0.00492892],
                'cup'  => ['name' => 'Cup',         'symbol' => 'cup',  'label' => 'Cup (cup)',         'ratio' => 0.23658824],
                'floz' => ['name' => 'Fluid Ounce', 'symbol' => 'fl oz','label' => 'Fluid Ounce (fl oz)','ratio' => 0.0295735],
                'gal'  => ['name' => 'Gallon',      'symbol' => 'gal',  'label' => 'Gallon (gal)',      'ratio' => 3.78541178],
            ],
        ],
        'UNIT' => [
            'base_unit' => 'pc',
            'units' => [
                'pc'      => ['name' => 'Piece',       'symbol' => 'pc',    'label' => 'Piece (pc)',        'ratio' => 1.0],
                'tray'    => ['name' => 'Tray',        'symbol' => 'tray',  'label' => 'Tray (24 pcs)',     'ratio' => 24.0],
                'doz'     => ['name' => 'Dozen',       'symbol' => 'doz',   'label' => 'Dozen (doz)',       'ratio' => 12.0],
                'halfdoz' => ['name' => 'Half Dozen',  'symbol' => '½ doz', 'label' => 'Half Dozen (½ doz)','ratio' => 6.0],
                'pair'    => ['name' => 'Pair',        'symbol' => 'pair',  'label' => 'Pair (pair)',       'ratio' => 2.0],
                'set'     => ['name' => 'Set',         'symbol' => 'set',   'label' => 'Set (set)',         'ratio' => 1.0],
            ],
        ],
        'LENGTH' => [
            'base_unit' => 'm',
            'units' => [
                'm'   => ['name' => 'Meters',      'symbol' => 'm',  'label' => 'Meters (m)',      'ratio' => 1.0],
                'cm'  => ['name' => 'Centimeters', 'symbol' => 'cm', 'label' => 'Centimeters (cm)', 'ratio' => 0.01],
                'mm'  => ['name' => 'Millimeters', 'symbol' => 'mm', 'label' => 'Millimeters (mm)', 'ratio' => 0.001],
                'in'  => ['name' => 'Inches',      'symbol' => 'in', 'label' => 'Inches (in)',      'ratio' => 0.0254],
                'ft'  => ['name' => 'Feet',        'symbol' => 'ft', 'label' => 'Feet (ft)',        'ratio' => 0.3048],
            ],
        ],
    ];


    /**
     * Normalize unit string to lookup key.
     */
    public function normalizeUnit(string $unit): string
    {
        return strtolower(trim(str_replace([' ', '.'], '', $unit)));
    }

    /**
     * Get canonical factor for a unit symbol within a measure type.
     */
    public function getCanonicalFactor(string $measureType, string $unitSymbol): float
    {
        $measureType = strtoupper(trim($measureType));
        $normalized = $this->normalizeUnit($unitSymbol);

        if (!isset(self::CANONICAL_FACTORS[$measureType])) {
            return 1.0;
        }

        foreach (self::CANONICAL_FACTORS[$measureType] as $key => $factor) {
            if ($this->normalizeUnit($key) === $normalized) {
                return (float) $factor;
            }
        }

        // Check if string contains known unit (e.g. "kg", "kls", "g", "l", "ml", "pc")
        if ($measureType === 'WEIGHT') {
            if (str_contains($normalized, 'kg') || str_contains($normalized, 'kilo')) return 1000.0;
            if (str_contains($normalized, 'mg')) return 0.001;
            if (str_contains($normalized, 'g')) return 1.0;
            if (str_contains($normalized, 'oz')) return 28.349523;
            if (str_contains($normalized, 'lb')) return 453.59237;
        } elseif ($measureType === 'VOLUME') {
            if (str_contains($normalized, 'ml')) return 1.0;
            if (str_contains($normalized, 'l') || str_contains($normalized, 'ltr')) return 1000.0;
            if (str_contains($normalized, 'gal')) return 3785.411784;
            if (str_contains($normalized, 'tbs')) return 14.786765;
            if (str_contains($normalized, 'tsp')) return 4.928922;
            if (str_contains($normalized, 'cup')) return 236.588237;
        } elseif ($measureType === 'UNIT') {
            if (str_contains($normalized, 'tray')) return 24.0;
            if (str_contains($normalized, 'doz')) return 12.0;
            if (str_contains($normalized, 'pair')) return 2.0;
            return 1.0;
        } elseif ($measureType === 'LENGTH') {
            if (str_contains($normalized, 'mm')) return 0.001;
            if (str_contains($normalized, 'cm')) return 0.01;
            if (str_contains($normalized, 'ft')) return 0.3048;
            if (str_contains($normalized, 'in')) return 0.0254;
            if (str_contains($normalized, 'm')) return 1.0;
        }

        return 1.0;
    }

    /**
     * Resolve measurement type string ('WEIGHT', 'VOLUME', 'UNIT', 'LENGTH')
     * for an item or unit of measure.
     */
    public function resolveMeasureType(Item|UnitOfMeasure|string|int $type): string
    {
        if ($type instanceof Item) {
            if (!empty($type->measurement_type)) {
                return strtoupper($type->measurement_type);
            }
            if ($type->unit) {
                return $this->resolveMeasureType($type->unit);
            }
            return 'UNIT';
        }

        if ($type instanceof UnitOfMeasure) {
            if ($type->measure_type_id) {
                $param = SystemParameter::find($type->measure_type_id);
                if ($param) {
                    return strtoupper($param->name);
                }
            }
            // Infer from measure_symbol or unit_symbol
            $symbol = $type->measure_symbol ?: $type->unit_symbol ?: '';
            return $this->inferTypeFromSymbol($symbol);
        }

        if (is_numeric($type)) {
            $param = SystemParameter::find($type);
            if ($param) {
                return strtoupper($param->name);
            }
            return 'UNIT';
        }

        $clean = strtoupper(trim($type));
        if (in_array($clean, ['WEIGHT', 'VOLUME', 'UNIT', 'LENGTH'])) {
            return $clean;
        }

        return $this->inferTypeFromSymbol($clean);
    }

    /**
     * Infer measure type from a symbol string.
     */
    protected function inferTypeFromSymbol(string $symbol): string
    {
        $norm = $this->normalizeUnit($symbol);
        if (empty($norm)) return 'UNIT';

        foreach (self::CANONICAL_FACTORS['WEIGHT'] as $k => $v) {
            if ($this->normalizeUnit($k) === $norm) return 'WEIGHT';
        }
        foreach (self::CANONICAL_FACTORS['VOLUME'] as $k => $v) {
            if ($this->normalizeUnit($k) === $norm) return 'VOLUME';
        }
        foreach (self::CANONICAL_FACTORS['LENGTH'] as $k => $v) {
            if ($this->normalizeUnit($k) === $norm) return 'LENGTH';
        }
        foreach (self::CANONICAL_FACTORS['UNIT'] as $k => $v) {
            if ($this->normalizeUnit($k) === $norm) return 'UNIT';
        }

        return 'UNIT';
    }

    /**
     * Automatically generate / upsert conversion matrix records in `unit_conversions`
     * upon Item creation or update.
     */
    public function generateConversionsForItem(Item $item): int
    {
        $uom = $item->unit;
        if (!$uom) {
            return 0;
        }

        $measureType = $this->resolveMeasureType($item);
        $baseSymbol = $uom->measure_symbol ?: $uom->unit_symbol ?: '';
        $baseValue = (float) ($uom->measure_value > 0 ? $uom->measure_value : 1.0);

        // Calculate total canonical quantity of the item's package
        $baseCanonicalFactor = $this->getCanonicalFactor($measureType, $baseSymbol);
        $totalPackagingInCanonical = $baseValue * $baseCanonicalFactor;

        // Find standard and active UOMs matching this measurement type
        $matchingUoms = UnitOfMeasure::query()
            ->where('status', 'ACTIVE')
            ->where(function ($q) use ($measureType, $uom) {
                if ($uom->measure_type_id) {
                    $q->where('measure_type_id', $uom->measure_type_id);
                }
                // Also match standard symbol names
                $standardKeys = array_keys(self::CANONICAL_FACTORS[$measureType] ?? []);
                foreach ($standardKeys as $key) {
                    $q->orWhere('unit_symbol', 'like', "%{$key}%")
                      ->orWhere('measure_symbol', 'like', "%{$key}%");
                }
            })
            ->get();

        // Always include the item's own base UOM
        if (!$matchingUoms->contains('id', $uom->id)) {
            $matchingUoms->push($uom);
        }

        $count = 0;

        DB::transaction(function () use ($item, $uom, $matchingUoms, $measureType, $totalPackagingInCanonical, &$count) {
            // 1. Conversions between item's packaging UOM and all matching units
            foreach ($matchingUoms as $targetUom) {
                $targetSymbol = $targetUom->measure_symbol ?: $targetUom->unit_symbol ?: '';
                $targetValue = (float) ($targetUom->measure_value > 0 ? $targetUom->measure_value : 1.0);
                $targetCanonicalFactor = $this->getCanonicalFactor($measureType, $targetSymbol);
                $targetCanonicalTotal = $targetValue * $targetCanonicalFactor;

                if ($targetCanonicalTotal <= 0) {
                    $targetCanonicalTotal = 1.0;
                }

                // Factor: How many target units are in 1 packaging unit?
                // Example: 1 [25kg sack] = 25,000g => factor = 25000.
                $factorFromPackaging = $totalPackagingInCanonical / $targetCanonicalTotal;
                // Factor: How many packaging units are in 1 target unit?
                // Example: 1g = 0.00004 [25kg sack]
                $factorToPackaging = $targetCanonicalTotal / ($totalPackagingInCanonical ?: 1.0);

                // Upsert packaging -> target
                UnitConvertion::updateOrCreate(
                    [
                        'item_id'     => $item->id,
                        'from_uom_id' => $uom->id,
                        'to_uom_id'   => $targetUom->id,
                    ],
                    [
                        'conversion_factor' => round($factorFromPackaging, 8),
                    ]
                );

                // Upsert target -> packaging
                UnitConvertion::updateOrCreate(
                    [
                        'item_id'     => $item->id,
                        'from_uom_id' => $targetUom->id,
                        'to_uom_id'   => $uom->id,
                    ],
                    [
                        'conversion_factor' => round($factorToPackaging, 8),
                    ]
                );

                $count += 2;
            }

            // 2. Cross-conversions between all standard units of this type for this item
            foreach ($matchingUoms as $uomA) {
                $symbolA = $uomA->measure_symbol ?: $uomA->unit_symbol ?: '';
                $valA = (float) ($uomA->measure_value > 0 ? $uomA->measure_value : 1.0);
                $canonA = $valA * $this->getCanonicalFactor($measureType, $symbolA);
                if ($canonA <= 0) $canonA = 1.0;

                foreach ($matchingUoms as $uomB) {
                    $symbolB = $uomB->measure_symbol ?: $uomB->unit_symbol ?: '';
                    $valB = (float) ($uomB->measure_value > 0 ? $uomB->measure_value : 1.0);
                    $canonB = $valB * $this->getCanonicalFactor($measureType, $symbolB);
                    if ($canonB <= 0) $canonB = 1.0;

                    $factorAB = $canonA / $canonB;

                    UnitConvertion::updateOrCreate(
                        [
                            'item_id'     => $item->id,
                            'from_uom_id' => $uomA->id,
                            'to_uom_id'   => $uomB->id,
                        ],
                        [
                            'conversion_factor' => round($factorAB, 8),
                        ]
                    );

                    // Also maintain a global cross-conversion if none exists
                    UnitConvertion::firstOrCreate(
                        [
                            'item_id'     => null,
                            'from_uom_id' => $uomA->id,
                            'to_uom_id'   => $uomB->id,
                        ],
                        [
                            'conversion_factor' => round($factorAB, 8),
                        ]
                    );

                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Resolve base unit packaging quantity and symbol for an item normalized to the category base unit.
     * Handles explicit measure_value & measure_symbol as well as legacy unit symbols (e.g. '2L', '25 kls.', '500g').
     */
    public function resolveBaseUnitDetails(Item $item): array
    {
        return $this->resolveItemBaseValue($item);
    }

    /**
     * Resolve item packaging quantity and symbol converted to normalized base unit.
     */
    public function resolveItemBaseValue(Item $item): array
    {
        $measureType = $this->resolveMeasureType($item);
        if (!isset(self::STANDARD_CONVERSIONS[$measureType])) {
            $measureType = 'UNIT';
        }

        $baseSymbol = self::STANDARD_CONVERSIONS[$measureType]['base_unit'];

        $itemUom = $item->unit;
        $qty = (float) ($itemUom?->measure_value ?? 0);
        $sym = trim($itemUom?->measure_symbol ?: '');
        $unitSym = trim($itemUom?->unit_symbol ?: '');

        // Extract numbers from legacy unit_symbol if measure_value is 0 (e.g. '2L' -> 2, '25 kls.' -> 25)
        if ($qty <= 0 || empty($sym)) {
            if (!empty($unitSym)) {
                if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z\.\/]+)?$/', $unitSym, $matches)) {
                    if ($qty <= 0 && isset($matches[1])) {
                        $qty = (float) $matches[1];
                    }
                    if (empty($sym) && !empty($matches[2])) {
                        $sym = trim($matches[2]);
                    }
                } else {
                    if (empty($sym)) {
                        $sym = $unitSym;
                    }
                }
            }
        }

        if ($qty <= 0) $qty = 1.0;
        if (empty($sym)) $sym = $unitSym ?: $baseSymbol;

        // Multiply pack qty by its ratio to base unit:
        // E.g. 500 g pack has ratio 0.001 -> 0.5 kg in base unit.
        // E.g. 25 kg pack has ratio 1.0 -> 25 kg in base unit.
        // E.g. 2 L bottle has ratio 1.0 -> 2 L in base unit.
        // E.g. 1 doz has ratio 12.0 -> 12 pc in base unit.
        $packRatio = $this->getUnitRatio($measureType, $sym);
        $measuredValueInBase = $qty * $packRatio;

        if ($measuredValueInBase <= 0) {
            $measuredValueInBase = 1.0;
        }

        return [
            'measure_type'           => $measureType,
            'base_symbol'            => $baseSymbol,
            'base_quantity'          => $measuredValueInBase,
            'raw_pack_qty'           => $qty,
            'raw_pack_symbol'        => $sym,
            'measured_value_in_base' => $measuredValueInBase,
        ];
    }

    /**
     * Get 1:1 conversion ratio for a unit within its specific measure type.
     * Converts from selected unit to category normalized base unit:
     * - In WEIGHT: 1 g = 0.001 kg, 1 kg = 1.0 kg
     * - In VOLUME: 1 mL = 0.001 L, 1 L = 1.0 L
     * - In UNIT:   1 doz = 12 pc,  1 pc = 1.0 pc
     * - In LENGTH: 1 cm = 0.01 m,  1 m = 1.0 m
     */
    public function getUnitRatio(string $measureType, $unitIdentifier): float
    {
        if (empty($unitIdentifier)) {
            return 1.0;
        }

        if (!isset(self::STANDARD_CONVERSIONS[$measureType])) {
            $measureType = 'UNIT';
        }

        $standardUnits = self::STANDARD_CONVERSIONS[$measureType]['units'];

        // 1. Direct key match (e.g. 'g', 'kg', 'ml', 'l', 'tbsp')
        $normKey = $this->normalizeUnit((string)$unitIdentifier);
        if (isset($standardUnits[$normKey])) {
            return (float) $standardUnits[$normKey]['ratio'];
        }

        // 2. Lookup symbol if numeric ID or model
        $symbol = '';
        if ($unitIdentifier instanceof UnitOfMeasure) {
            $symbol = $unitIdentifier->measure_symbol ?: $unitIdentifier->unit_symbol ?: '';
        } elseif (is_numeric($unitIdentifier)) {
            $uomRecord = UnitOfMeasure::find($unitIdentifier);
            $symbol = $uomRecord ? ($uomRecord->measure_symbol ?: $uomRecord->unit_symbol ?: '') : '';
        } elseif (is_string($unitIdentifier)) {
            $symbol = $unitIdentifier;
        }

        $norm = $this->normalizeUnit($symbol);
        if (empty($norm)) {
            return 1.0;
        }

        // Check if norm matches any standard key or symbol in this measure type
        foreach ($standardUnits as $k => $def) {
            if ($this->normalizeUnit($k) === $norm || $this->normalizeUnit($def['symbol']) === $norm || $this->normalizeUnit($def['name']) === $norm) {
                return (float) $def['ratio'];
            }
        }

        // Strictly scoped fallback within this measureType only (NO CROSS-CATEGORY):
        if ($measureType === 'WEIGHT') {
            if (str_contains($norm, 'mg')) return 0.000001;
            if (str_contains($norm, 'kg') || str_contains($norm, 'kilo') || str_contains($norm, 'kl')) return 1.0;
            if (str_contains($norm, 'g')) return 0.001;
            if (str_contains($norm, 'lb')) return 0.45359237;
            if (str_contains($norm, 'oz')) return 0.02834952;
            return 1.0;
        } elseif ($measureType === 'VOLUME') {
            if (str_contains($norm, 'ml')) return 0.001;
            if (str_contains($norm, 'gal')) return 3.78541178;
            if (str_contains($norm, 'tbsp') || str_contains($norm, 'tbs')) return 0.0147868;
            if (str_contains($norm, 'tsp')) return 0.00492892;
            if (str_contains($norm, 'cup')) return 0.23658824;
            if (str_contains($norm, 'floz')) return 0.0295735;
            if (str_contains($norm, 'l') || str_contains($norm, 'ltr')) return 1.0;
            return 1.0;
        } elseif ($measureType === 'UNIT') {
            if (str_contains($norm, 'tray')) return 24.0;
            if (str_contains($norm, 'halfdoz') || str_contains($norm, '½doz')) return 6.0;
            if (str_contains($norm, 'doz')) return 12.0;
            if (str_contains($norm, 'pair')) return 2.0;
            return 1.0;
        } elseif ($measureType === 'LENGTH') {
            if (str_contains($norm, 'mm')) return 0.001;
            if (str_contains($norm, 'cm')) return 0.01;
            if (str_contains($norm, 'in')) return 0.0254;
            if (str_contains($norm, 'ft')) return 0.3048;
            if (str_contains($norm, 'm')) return 1.0;
            return 1.0;
        }

        return 1.0;
    }

    /**
     * Resolve clean target unit symbol for display.
     */
    public function resolveUnitSymbol(string $measureType, $unitIdentifier): string
    {
        if (!isset(self::STANDARD_CONVERSIONS[$measureType])) {
            $measureType = 'UNIT';
        }

        $standardUnits = self::STANDARD_CONVERSIONS[$measureType]['units'];

        $normKey = $this->normalizeUnit((string)$unitIdentifier);
        if (isset($standardUnits[$normKey])) {
            return $standardUnits[$normKey]['symbol'];
        }

        $symbol = '';
        if ($unitIdentifier instanceof UnitOfMeasure) {
            $symbol = $unitIdentifier->measure_symbol ?: $unitIdentifier->unit_symbol ?: '';
        } elseif (is_numeric($unitIdentifier)) {
            $uomRecord = UnitOfMeasure::find($unitIdentifier);
            $symbol = $uomRecord ? ($uomRecord->measure_symbol ?: $uomRecord->unit_symbol ?: '') : '';
        } elseif (is_string($unitIdentifier)) {
            $symbol = $unitIdentifier;
        }

        $norm = $this->normalizeUnit($symbol);
        foreach ($standardUnits as $k => $def) {
            if ($this->normalizeUnit($k) === $norm || $this->normalizeUnit($def['symbol']) === $norm || $this->normalizeUnit($def['name']) === $norm) {
                return $def['symbol'];
            }
        }

        return $symbol ?: self::STANDARD_CONVERSIONS[$measureType]['base_unit'];
    }

    /**
     * Convert selected quantity from one UOM to the Item's category base unit.
     */
    public function convertToBaseUnit(Item $item, float $selectedQty, $selectedUom): float
    {
        $measureType = $this->resolveMeasureType($item);
        $targetRatio = $this->getUnitRatio($measureType, $selectedUom);
        return $selectedQty * $targetRatio;
    }

    /**
     * Calculate Recipe Ingredient Cost according to exact standard formula:
     * Unit Cost = Item Purchase Price / Item Measured Value in Base Unit
     * Line Cost = Portion Qty * Target Unit Ratio * Unit Cost
     *
     * Example:
     * Pork Kasim (1 kg @ PHP 320.00) -> Unit Cost = PHP 320.00 / kg.
     * User Selects: Portion Qty = 250, Recipe Unit = g (ratio: 0.001)
     * Portion in Base = 250 * 0.001 = 0.25 kg.
     * Line Cost = 0.25 * 320.00 = PHP 80.00.
     */
    public function calculateIngredientCost(Item $item, float $selectedQty, $selectedUom, ?float $customPrice = null): array
    {
        $purchasePrice = $customPrice !== null ? (float)$customPrice : (float) ($item->cost?->amount ?? 0.0);
        $baseInfo = $this->resolveItemBaseValue($item);
        $measureType = $baseInfo['measure_type'];
        $baseSymbol = $baseInfo['base_symbol'];
        $itemMeasuredValue = (float) $baseInfo['measured_value_in_base'];

        // Unit Cost = Item Purchase Price / Item Measured Value in Base Unit
        $unitCost = $itemMeasuredValue > 0 ? ($purchasePrice / $itemMeasuredValue) : $purchasePrice;

        // Target Unit Ratio & Symbol
        $targetRatio = $this->getUnitRatio($measureType, $selectedUom);
        $targetSymbol = $this->resolveUnitSymbol($measureType, $selectedUom);

        // Portion in Base Unit = Portion Qty * Target Unit Ratio
        $portionInBase = $selectedQty * $targetRatio;

        // Line Cost = Portion in Base Unit * Unit Cost
        $lineCost = $portionInBase * $unitCost;

        return [
            'purchase_price'           => $purchasePrice,
            'base_package_quantity'    => $itemMeasuredValue,
            'base_symbol'              => $baseSymbol,
            'unit_cost'                => round($unitCost, 4),
            'target_unit_symbol'       => $targetSymbol,
            'target_unit_ratio'        => $targetRatio,
            'qty_converted_to_base'    => round($portionInBase, 5),
            'cost'                     => round($lineCost, 2),
            // Aliases for component backward-compatibility:
            'total_package_cost'       => $purchasePrice,
            'cost_per_base_unit'       => round($unitCost, 4),
        ];
    }

    /**
     * Get list of compatible units strictly scoped to the item's measure type.
     * Formats options cleanly (e.g. Grams (g), Kilograms (kg)) and eliminates cross-category noise.
     */
    public function getCompatibleUnitsForItem(Item $item): Collection
    {
        $measureType = $this->resolveMeasureType($item);

        if (!isset(self::STANDARD_CONVERSIONS[$measureType])) {
            $measureType = 'UNIT';
        }

        $standardList = self::STANDARD_CONVERSIONS[$measureType]['units'];
        $baseUnit = self::STANDARD_CONVERSIONS[$measureType]['base_unit'];

        // Find active UnitOfMeasure records to map IDs where available
        $uomMap = UnitOfMeasure::query()
            ->where('status', 'ACTIVE')
            ->get();

        $param = SystemParameter::where('key', 'measure_type')
            ->where('name', $measureType)
            ->first();

        $results = [];
        foreach ($standardList as $key => $unitDef) {
            $normKey = $this->normalizeUnit($key);
            $normSym = $this->normalizeUnit($unitDef['symbol']);
            $dbUom = $uomMap->first(function ($u) use ($normKey, $normSym) {
                $sym = $this->normalizeUnit($u->measure_symbol ?: $u->unit_symbol ?: '');
                $name = $this->normalizeUnit($u->unit_name ?: '');
                return $sym === $normKey || $sym === $normSym || $name === $normKey;
            });

            if (!$dbUom) {
                try {
                    $dbUom = UnitOfMeasure::firstOrCreate(
                        ['unit_symbol' => $unitDef['symbol']],
                        [
                            'unit_name'       => $unitDef['name'],
                            'measure_symbol'  => $unitDef['symbol'],
                            'measure_type_id' => $param?->id,
                            'measure_value'   => 1.0,
                            'status'          => 'ACTIVE',
                            'company_id'      => 1,
                        ]
                    );
                    $uomMap->push($dbUom);
                } catch (\Throwable $e) {
                    // Fallback
                }
            }

            // Use DB UOM ID if found, otherwise the clean unit key
            $id = $dbUom ? $dbUom->id : $key;

            $results[] = [
                'id'         => $id,
                'key'        => $key,
                'name'       => $unitDef['name'],
                'symbol'     => $unitDef['symbol'],
                'label'      => $unitDef['label'],
                'ratio'      => $unitDef['ratio'],
                'base_unit'  => $baseUnit,
            ];
        }

        return collect($results);
    }

    /**
     * Calculate dual-cost metrics comparing the approved baseline cost
     * against the live current market cost derived from the latest PO deliveries.
     */
    public function calculateRecipeLiveMetrics(Recipe $recipe): array
    {
        $ingredientsData = [];
        $currentTotalCost = 0.0;
        $approvedTotalCost = (float) $recipe->total_cost;

        foreach ($recipe->ingredients as $ingredient) {
            $item = $ingredient->item;
            if (!$item) continue;

            $calc = $this->calculateIngredientCost($item, (float) $ingredient->qty, $ingredient->uom_id);
            $lineCurrentCost = (float) $calc['cost'];
            $lineApprovedCost = (float) ($ingredient->cost > 0 ? $ingredient->cost : $lineCurrentCost);
            $lineDiff = round($lineCurrentCost - $lineApprovedCost, 2);
            $lineDiffPercent = $lineApprovedCost > 0 ? round(($lineDiff / $lineApprovedCost) * 100, 1) : 0.0;

            $currentTotalCost += $lineCurrentCost;

            $ingredientsData[] = [
                'ingredient_id'          => $ingredient->id,
                'item'                   => $item,
                'qty'                    => (float) $ingredient->qty,
                'uom_id'                 => $ingredient->uom_id,
                'unit_symbol'            => $calc['target_unit_symbol'] ?: ($ingredient->unit ? ($ingredient->unit->measure_symbol ?: $ingredient->unit->unit_symbol) : 'N/A'),
                'base_qty'               => $calc['qty_converted_to_base'],
                'base_symbol'            => $calc['base_symbol'],
                'package_val'            => $calc['base_package_quantity'],
                'package_cost'           => (float) ($item->cost?->amount ?? 0),
                'unit_cost'              => $calc['cost_per_base_unit'],
                'line_cost'              => $lineCurrentCost,
                'approved_line_cost'     => $lineApprovedCost,
                'line_variance'          => $lineDiff,
                'line_variance_percent'  => $lineDiffPercent,
                'line_status'            => abs($lineDiff) < 0.05 ? 'STABLE' : ($lineDiff > 0 ? 'INCREASED' : 'DECREASED'),
            ];
        }

        $currentTotalCost = round($currentTotalCost, 2);
        if ($approvedTotalCost <= 0) {
            $approvedTotalCost = $currentTotalCost;
        }

        $diff = round($currentTotalCost - $approvedTotalCost, 2);
        $diffPercent = $approvedTotalCost > 0 ? round(($diff / $approvedTotalCost) * 100, 1) : 0.0;

        $status = 'STABLE';
        if (abs($diff) >= 0.50 && abs($diffPercent) >= 0.5) {
            $status = $diff > 0 ? 'INCREASED' : 'DECREASED';
        }

        $servings = (float) ($recipe->serving_size > 0 ? $recipe->serving_size : 1.0);
        $approvedCostPerServing = round($approvedTotalCost / $servings, 2);
        $currentCostPerServing = round($currentTotalCost / $servings, 2);

        $sellingPrice = (float) ($recipe->rate?->amount ?? 0.0);
        $approvedFoodCostPercent = $sellingPrice > 0 ? round(($approvedCostPerServing / $sellingPrice) * 100, 1) : 0.0;
        $currentFoodCostPercent = $sellingPrice > 0 ? round(($currentCostPerServing / $sellingPrice) * 100, 1) : 0.0;
        $approvedGrossMargin = round($sellingPrice - $approvedCostPerServing, 2);
        $currentGrossMargin = round($sellingPrice - $currentCostPerServing, 2);

        return [
            'approved_cost'              => $approvedTotalCost,
            'current_cost'               => $currentTotalCost,
            'variance_amount'            => $diff,
            'variance_percent'           => $diffPercent,
            'variance_status'            => $status, // 'STABLE', 'INCREASED', 'DECREASED'
            'servings'                   => $servings,
            'approved_cost_per_serving'  => $approvedCostPerServing,
            'current_cost_per_serving'   => $currentCostPerServing,
            'selling_price'              => $sellingPrice,
            'approved_food_cost_percent' => $approvedFoodCostPercent,
            'current_food_cost_percent'  => $currentFoodCostPercent,
            'approved_gross_margin'      => $approvedGrossMargin,
            'current_gross_margin'       => $currentGrossMargin,
            'ingredients'                => $ingredientsData,
        ];
    }

    /**
     * Get historical cost trajectory comparing PO market price variations vs approved baseline over time.
     */
    public function getRecipeCostTrajectory(Recipe $recipe): array
    {
        $recipe->loadMissing(['ingredients.item.cost', 'ingredients.unit']);

        $ingredients = $recipe->ingredients;
        $itemIds = $ingredients->pluck('item_id')->filter()->unique()->values();

        // 1. Fetch historical item cost prices from PO deliveries or manual changes
        $itemPrices = Price::whereIn('item_id', $itemIds)
            ->where('price_type', 'COST')
            ->orderBy('created_at', 'asc')
            ->get();

        // 2. Fetch historical recipe approved baseline costs
        $baselinePrices = Price::where('menu_id', $recipe->id)
            ->where('price_type', 'COST')
            ->orderBy('created_at', 'asc')
            ->get();

        // 3. Collect unique event dates
        $dateTimestamps = collect();
        if ($recipe->created_at) {
            $dateTimestamps->push($recipe->created_at);
        }
        foreach ($itemPrices as $ip) {
            if ($ip->created_at) {
                $dateTimestamps->push($ip->created_at);
            }
        }
        foreach ($baselinePrices as $bp) {
            if ($bp->created_at) {
                $dateTimestamps->push($bp->created_at);
            }
        }
        $dateTimestamps->push(now());

        // Sort chronologically and format
        $uniqueDates = $dateTimestamps
            ->sortBy(fn($d) => $d->timestamp)
            ->map(fn($d) => $d->format('M d, Y'))
            ->unique()
            ->values();

        // Group prices by item for fast lookup
        $pricesByItem = $itemPrices->groupBy('item_id');

        $marketCostSeries = [];
        $baselineCostSeries = [];

        $recipeInitialCost = (float) $recipe->total_cost;
        if ($recipeInitialCost <= 0 && $baselinePrices->isNotEmpty()) {
            $recipeInitialCost = (float) $baselinePrices->first()->amount;
        }

        foreach ($uniqueDates as $formattedDate) {
            $targetDate = Carbon::parse($formattedDate)->endOfDay();

            // Calculate market cost on that date
            $dateMarketTotal = 0.0;
            foreach ($ingredients as $ingredient) {
                $item = $ingredient->item;
                if (!$item) continue;

                $itemHist = $pricesByItem->get($item->id, collect());
                $effectivePrice = $itemHist->filter(fn($p) => $p->created_at <= $targetDate)->last();

                if (!$effectivePrice) {
                    $effectivePrice = $itemHist->first() ?: $item->cost;
                }

                $packageCost = $effectivePrice ? (float)$effectivePrice->amount : (float)($item->cost?->amount ?? 0);
                $calc = $this->calculateIngredientCost($item, (float)$ingredient->qty, $ingredient->uom_id, $packageCost);
                $dateMarketTotal += $calc['cost'];
            }

            // Find effective baseline cost on that date
            $effectiveBaseline = $baselinePrices->filter(fn($p) => $p->created_at <= $targetDate)->last();
            $dateBaseline = $effectiveBaseline ? (float)$effectiveBaseline->amount : $recipeInitialCost;
            if ($dateBaseline <= 0) {
                $dateBaseline = $dateMarketTotal;
            }

            $marketCostSeries[] = round($dateMarketTotal, 2);
            $baselineCostSeries[] = round($dateBaseline, 2);
        }

        // Build list of recent PO delivery price changes impacting this recipe
        $recentChanges = [];
        foreach ($itemPrices->sortByDesc('created_at')->take(10) as $ip) {
            $matchingIng = $ingredients->firstWhere('item_id', $ip->item_id);
            if (!$matchingIng) continue;

            $item = $matchingIng->item;
            $calc = $this->calculateIngredientCost($item, (float)$matchingIng->qty, $matchingIng->uom_id, (float)$ip->amount);

            $recentChanges[] = [
                'date'          => $ip->created_at ? $ip->created_at->format('M d, Y') : 'N/A',
                'item_name'     => $item?->item_description ?? 'N/A',
                'item_code'     => $item?->item_code ?? 'N/A',
                'new_cost'      => (float) $ip->amount,
                'recipe_impact' => $calc['cost'],
                'supplier'      => $ip->supplier?->supplier_name ?? 'Standard / Manual',
            ];
        }

        // Handle single date point: provide 2 points so the chart line renders smoothly
        $chartLabels = $uniqueDates->toArray();
        if (count($chartLabels) <= 1) {
            $onlyLabel = $chartLabels[0] ?? now()->format('M d, Y');
            $chartLabels = ['Initial Baseline', $onlyLabel];
            $marketCostSeries = [$marketCostSeries[0] ?? $recipeInitialCost, $marketCostSeries[0] ?? $recipeInitialCost];
            $baselineCostSeries = [$baselineCostSeries[0] ?? $recipeInitialCost, $baselineCostSeries[0] ?? $recipeInitialCost];
        }

        return [
            'labels'         => $chartLabels,
            'series'         => [
                ['name' => 'Market Cost (PO)', 'data' => $marketCostSeries],
                ['name' => 'Approved Baseline', 'data' => $baselineCostSeries],
            ],
            'recent_changes' => $recentChanges,
            'has_history'    => count($recentChanges) > 0,
        ];
    }
}

