<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;
use App\Models\DataManagement\Recipe;
use App\Models\DataManagement\Ingredient;
use App\Models\DataManagement\Price;
use App\Services\DataManagement\UnitConversionService;
use Illuminate\Support\Facades\DB;

echo "=== TEST: DUAL-COSTING ARCHITECTURE (APPROVED VS LIVE PO COST) ===\n";

$service = app(UnitConversionService::class);
$porkKasim = Item::find(5192);

// 1. Setup a test recipe
$testRecipe = Recipe::firstOrCreate(
    ['menu_code' => 'TEST-DUAL-COST'],
    [
        'menu_name'    => 'Test Pork Dish',
        'category_id'  => 1,
        'status'       => 'AVAILABLE',
        'serving_size' => 2,
        'total_cost'   => 80.00, // Approved baseline cost
        'company_id'   => 1,
    ]
);

// Ensure ingredient attached: 250 g Pork Kasim
Ingredient::updateOrCreate(
    ['menu_id' => $testRecipe->id, 'item_id' => $porkKasim->id],
    [
        'qty'    => 250,
        'uom_id' => 28, // Grams (g)
        'cost'   => 80.00,
    ]
);

$testRecipe->load(['ingredients.item.cost', 'ingredients.item.unit', 'ingredients.unit']);

echo "Step 1: Initial Recipe State\n";
$metrics = $service->calculateRecipeLiveMetrics($testRecipe);
echo "  - Approved Cost: PHP " . number_format($metrics['approved_cost'], 2) . "\n";
echo "  - Current Live Cost: PHP " . number_format($metrics['current_cost'], 2) . "\n";
echo "  - Variance Status: {$metrics['variance_status']}\n";
assert($metrics['approved_cost'] == 80.00, "Approved cost must be 80.00");
assert($metrics['current_cost'] == 80.00, "Current live cost must match initial price");
assert($metrics['variance_status'] == 'STABLE', "Initial status must be STABLE");
echo "✓ Initial state verified (Aligned & Stable).\n\n";

// 2. Simulate a PO Receiving with price increase (PHP 320 -> PHP 400 per kg)
echo "Step 2: Simulating PO Delivery with Price Hike (PHP 320.00 -> PHP 400.00 / kg)\n";
$newPoPrice = Price::create([
    'item_id'    => $porkKasim->id,
    'price_type' => 'COST',
    'amount'     => 400.00,
    'company_id' => 1,
    'branch_id'  => 2,
    'created_at' => now()->addSecond(),
]);

// Reload relations
$testRecipe->load(['ingredients.item.cost', 'ingredients.item.unit', 'ingredients.unit']);
$metricsAfterPo = $service->calculateRecipeLiveMetrics($testRecipe);

echo "  - Approved Baseline Cost: PHP " . number_format($metricsAfterPo['approved_cost'], 2) . " (PRESERVED!)\n";
echo "  - Current Live PO Cost: PHP " . number_format($metricsAfterPo['current_cost'], 2) . " (250g @ 400/kg = PHP 100.00)\n";
echo "  - Variance Amount: +PHP " . number_format($metricsAfterPo['variance_amount'], 2) . "\n";
echo "  - Variance Percent: +{$metricsAfterPo['variance_percent']}%\n";
echo "  - Variance Status: {$metricsAfterPo['variance_status']}\n";

assert($metricsAfterPo['approved_cost'] == 80.00, "Approved baseline cost MUST NOT change silently!");
assert($metricsAfterPo['current_cost'] == 100.00, "Live current cost must dynamically reflect the PO price (PHP 100.00)!");
assert($metricsAfterPo['variance_amount'] == 20.00, "Variance amount must be PHP 20.00");
assert($metricsAfterPo['variance_status'] == 'INCREASED', "Status must be INCREASED");
echo "✓ Dual-Costing verified: Baseline preserved while Live PO Cost accurately tracked inflation!\n\n";

// 3. Test Sync Action (Chef / Manager signs off and updates standard cost)
echo "Step 3: Simulating 'Sync Standard Cost to Current PO Prices'\n";
$testRecipe->update(['total_cost' => $metricsAfterPo['current_cost']]);
foreach ($testRecipe->ingredients as $ing) {
    $ing->update(['cost' => 100.00]);
}

$testRecipe->load(['ingredients.item.cost', 'ingredients.item.unit', 'ingredients.unit']);
$metricsSynced = $service->calculateRecipeLiveMetrics($testRecipe);

echo "  - Synced Approved Cost: PHP " . number_format($metricsSynced['approved_cost'], 2) . "\n";
echo "  - Current Cost: PHP " . number_format($metricsSynced['current_cost'], 2) . "\n";
echo "  - Variance Status: {$metricsSynced['variance_status']}\n";
assert($metricsSynced['approved_cost'] == 100.00, "Approved cost must now be synced to 100.00");
assert($metricsSynced['variance_status'] == 'STABLE', "Status must return to STABLE");
echo "✓ Sync Action successfully updated baseline cost to new market reality.\n\n";

// Cleanup test price and recipe
$newPoPrice->delete();
$testRecipe->delete();
echo "=== ALL DUAL-COSTING TESTS PASSED PERFECTLY! ===\n";
