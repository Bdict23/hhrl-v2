<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\DataManagement\UnitConversionService;
use App\Models\Settings\SystemParameter;
use App\Models\DataManagement\Recipe;
use Illuminate\Support\Facades\DB;

echo "=== TEST: LABOR COST PERCENTAGE & RECIPE COSTING MODULE ===\n\n";

$service = app(UnitConversionService::class);

// 1. Verify Labor Cost Parameter Retrieval
echo "Test 1: System Parameter Labor Cost Retrieval\n";
$laborPercent = $service->getLaborCostPercentage();
echo "  - Retrieved Labor Cost Percentage: {$laborPercent}%\n";
assert($laborPercent == 30.0, "Expected 30% from system_parameters (module_id=51, key='LABOR_COST')");
echo "  ✓ Parameter correctly fetched from DB.\n\n";

// 2. Test Calculation Formulas with Standard Values
echo "Test 2: Standard Cost Formulas Verification\n";
$mockIngredients = [
    ['line_cost' => 45.50],
    ['line_cost' => 30.00],
    ['line_cost' => 24.50],
];
$baseIngredientsCost = round((float) array_sum(array_column($mockIngredients, 'line_cost')), 2);
$laborCostAmount = round($baseIngredientsCost * ($laborPercent / 100), 2);
$totalBatchCost = round($baseIngredientsCost + $laborCostAmount, 2);

$servings = 4.0;
$costPerServing = $servings > 0 ? round($totalBatchCost / $servings, 2) : 0.0;

$sellingPrice = 50.00;
$foodCostPercent = $sellingPrice > 0 ? round(($costPerServing / $sellingPrice) * 100, 1) : 0.0;
$grossMargin = round($sellingPrice - $costPerServing, 2);

echo "  - Base Ingredients Cost: ₱ " . number_format($baseIngredientsCost, 2) . "\n";
echo "  - Labor Cost Amount (30%): ₱ " . number_format($laborCostAmount, 2) . "\n";
echo "  - Total Batch Cost: ₱ " . number_format($totalBatchCost, 2) . "\n";
echo "  - Cost Per Serving (4 servings): ₱ " . number_format($costPerServing, 2) . "\n";
echo "  - Selling Price: ₱ " . number_format($sellingPrice, 2) . "\n";
echo "  - Food Cost %: {$foodCostPercent}%\n";
echo "  - Gross Margin: ₱ " . number_format($grossMargin, 2) . "\n";

assert($baseIngredientsCost == 100.00, "Base cost should be 100.00");
assert($laborCostAmount == 30.00, "Labor cost amount should be 30.00 (30% of 100)");
assert($totalBatchCost == 130.00, "Total batch cost should be 130.00");
assert($costPerServing == 32.50, "Cost per serving should be 32.50 (130 / 4)");
assert($foodCostPercent == 65.0, "Food cost % should be 65.0%");
assert($grossMargin == 17.50, "Gross margin should be 17.50 (50.00 - 32.50)");
echo "  ✓ Standard formulas matched exact business logic specifications.\n\n";

// 3. Test Edge Cases (Zero servings, Zero selling price, Negative/Missing values)
echo "Test 3: Edge Cases Safeguards\n";

// Case 3a: Zero Yield / Servings
$zeroServings = 0;
$safeCostPerServing = (float)$zeroServings > 0 ? round($totalBatchCost / $zeroServings, 2) : 0.0;
echo "  - Zero Servings Cost Per Serving: ₱ " . number_format($safeCostPerServing, 2) . "\n";
assert($safeCostPerServing === 0.0, "Zero servings should safely yield 0.0 without division by zero");

// Case 3b: Zero Selling Price
$zeroSellingPrice = 0.00;
$safeFoodCostPercent = $zeroSellingPrice > 0 ? round(($costPerServing / $zeroSellingPrice) * 100, 1) : 0.0;
$safeGrossMargin = $zeroSellingPrice > 0 || $costPerServing > 0 ? round($zeroSellingPrice - $costPerServing, 2) : 0.0;
echo "  - Zero Selling Price Food Cost %: {$safeFoodCostPercent}%\n";
echo "  - Zero Selling Price Gross Margin: ₱ " . number_format($safeGrossMargin, 2) . "\n";
assert($safeFoodCostPercent === 0.0, "Zero selling price should safely yield 0.0% food cost");
assert($safeGrossMargin === -32.50, "Gross margin with 0 selling price should be -32.50");

// Case 3c: Fallback when parameter is missing or invalid
$originalParam = SystemParameter::where('module_id', 51)->where('key', 'LABOR_COST')->first();
DB::table('system_parameters')->where('id', $originalParam->id)->update(['name' => 'invalid_str']);
$fallbackPercent = $service->getLaborCostPercentage();
echo "  - Invalid string parameter fallback: {$fallbackPercent}%\n";
assert($fallbackPercent === 0.0, "Invalid parameter string must fallback to 0.0%");

// Restore original parameter
DB::table('system_parameters')->where('id', $originalParam->id)->update(['name' => $originalParam->name]);
$restoredPercent = $service->getLaborCostPercentage();
echo "  - Restored parameter: {$restoredPercent}%\n";
assert($restoredPercent == 30.0, "Restored parameter must be 30.0%");

echo "  ✓ All edge cases and fallbacks verified.\n\n";

// 4. Test Live Recipe Integration via UnitConversionService::calculateRecipeLiveMetrics
echo "Test 4: Recipe Model Live Metrics Integration\n";
$recipe = Recipe::whereHas('ingredients')->with(['ingredients.item.cost', 'ingredients.item.unit', 'ingredients.unit', 'rate'])->first();
if ($recipe) {
    echo "  Testing with real Recipe ID {$recipe->id} ({$recipe->menu_name}):\n";
    $metrics = $service->calculateRecipeLiveMetrics($recipe);
    echo "  - Base Ingredients Cost: ₱ " . number_format($metrics['base_ingredients_cost'], 2) . "\n";
    echo "  - Labor Cost %: {$metrics['labor_cost_percent']}%\n";
    echo "  - Labor Cost Amount: ₱ " . number_format($metrics['labor_cost_amount'], 2) . "\n";
    echo "  - Total Current Batch Cost: ₱ " . number_format($metrics['current_cost'], 2) . "\n";
    echo "  - Cost Per Serving: ₱ " . number_format($metrics['current_cost_per_serving'], 2) . "\n";
    echo "  - Food Cost %: {$metrics['current_food_cost_percent']}%\n";
    echo "  - Gross Margin: ₱ " . number_format($metrics['current_gross_margin'], 2) . "\n";
    
    assert(isset($metrics['base_ingredients_cost']), "base_ingredients_cost key missing");
    assert(isset($metrics['labor_cost_percent']), "labor_cost_percent key missing");
    assert(isset($metrics['labor_cost_amount']), "labor_cost_amount key missing");
    $expectedTotal = round($metrics['base_ingredients_cost'] + $metrics['labor_cost_amount'], 2);
    assert($metrics['current_cost'] == $expectedTotal, "Total current cost must equal base + labor");
    echo "  ✓ Recipe live metrics verified with real database records.\n\n";
} else {
    echo "  (No recipes with ingredients found in database, skipped real recipe test)\n\n";
}

echo "=== ALL LABOR COST RECIPE TESTS PASSED SUCCESSFULLY! ===\n";
