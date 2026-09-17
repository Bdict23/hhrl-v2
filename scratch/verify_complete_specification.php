<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;
use App\Services\DataManagement\UnitConversionService;

$service = app(UnitConversionService::class);

echo "=== TEST 1: STRICT CATEGORY SCOPING ===\n";
// Weight item: Pork Kasim
$porkKasim = Item::find(5192);
$porkUnits = $service->getCompatibleUnitsForItem($porkKasim);
echo "Pork Kasim (measured_type: {$porkKasim->measurement_type}) Units:\n";
$weightSymbols = [];
foreach ($porkUnits as $u) {
    echo "  - [ID: {$u['id']}] {$u['label']} (Ratio to {$u['base_unit']}: {$u['ratio']})\n";
    $weightSymbols[] = $u['symbol'];
    if (in_array($u['symbol'], ['L', 'mL', 'gal', 'tbsp', 'tsp', 'cup', 'fl oz'])) {
        throw new \Exception("VIOLATION: Cross-category volume unit found in Weight item!");
    }
}
echo "✓ Weight category strict scoping PASSED (0 volume units found).\n\n";

// Volume item
$oil = Item::where('measurement_type', 'VOLUME')->first();
if (!$oil) {
    $oil = Item::find(109); // temporary instance
    $oil->measurement_type = 'VOLUME';
}
$oilUnits = $service->getCompatibleUnitsForItem($oil);
echo "Oil (measured_type: {$oil->measurement_type}) Units:\n";
foreach ($oilUnits as $u) {
    echo "  - [ID: {$u['id']}] {$u['label']} (Ratio to {$u['base_unit']}: {$u['ratio']})\n";
    if (in_array($u['symbol'], ['kg', 'g', 'mg', 'lbs', 'oz'])) {
        throw new \Exception("VIOLATION: Cross-category weight unit found in Volume item!");
    }
}
echo "✓ Volume category strict scoping PASSED (0 weight units found).\n\n";

echo "=== TEST 2: STANDARDIZED 1:1 UNIT RATIO CONVERSIONS ===\n";
// 1 kg = 1000 g => 1 g = 0.001 kg
$gRatio = $service->getUnitRatio('WEIGHT', 'g');
$kgRatio = $service->getUnitRatio('WEIGHT', 'kg');
echo "1 g = {$gRatio} kg\n";
echo "1 kg = {$kgRatio} kg\n";
assert($gRatio == 0.001, "g ratio must be 0.001");
assert($kgRatio == 1.0, "kg ratio must be 1.0");

// 1 L = 1000 mL => 1 mL = 0.001 L
$mlRatio = $service->getUnitRatio('VOLUME', 'ml');
$lRatio = $service->getUnitRatio('VOLUME', 'l');
echo "1 mL = {$mlRatio} L\n";
echo "1 L = {$lRatio} L\n";
assert($mlRatio == 0.001, "ml ratio must be 0.001");
assert($lRatio == 1.0, "l ratio must be 1.0");
echo "✓ Standard 1:1 ratios verified.\n\n";

echo "=== TEST 3: DYNAMIC COSTING FORMULA (VERIFICATION EXAMPLE) ===\n";
// Item: Pork Kasim (1 kg @ PHP 320.00)
// User Selects: Portion Qty = 250, Recipe Unit = g
// Conversion Ratio: 250 g = 0.25 kg
// Calculated Line Cost: 0.25 * 320.00 = PHP 80.00
$calcKasim = $service->calculateIngredientCost($porkKasim, 250, 'g');
echo "Pork Kasim Calculation:\n";
echo "  - Purchase Price: PHP " . number_format($calcKasim['purchase_price'], 2) . "\n";
echo "  - Base Package Qty: {$calcKasim['base_package_quantity']} {$calcKasim['base_symbol']}\n";
echo "  - Base Unit Cost: PHP " . number_format($calcKasim['unit_cost'], 2) . " / {$calcKasim['base_symbol']}\n";
echo "  - Portion: 250 {$calcKasim['target_unit_symbol']} (Ratio: {$calcKasim['target_unit_ratio']})\n";
echo "  - Converted to Base: {$calcKasim['qty_converted_to_base']} {$calcKasim['base_symbol']}\n";
echo "  - Line Cost: PHP " . number_format($calcKasim['cost'], 2) . "\n";
assert($calcKasim['cost'] == 80.00, "Pork Kasim line cost must be exactly PHP 80.00!");
echo "✓ Exact prompt verification example matches PHP 80.00!\n\n";

echo "=== TEST 4: ALL COMPATIBLE UNITS HAVE VALID NUMERIC DATABASE IDs ===\n";
foreach ($porkUnits as $u) {
    if (!is_numeric($u['id'])) {
        throw new \Exception("VIOLATION: Unit '{$u['name']}' has non-numeric ID: {$u['id']}");
    }
}
foreach ($oilUnits as $u) {
    if (!is_numeric($u['id'])) {
        throw new \Exception("VIOLATION: Unit '{$u['name']}' has non-numeric ID: {$u['id']}");
    }
}
echo "✓ All compatible unit IDs are valid database integers for recipes.uom_id.\n\n";

echo "=== ALL VERIFICATIONS PASSED SUCCESSFULLY! ===\n";
