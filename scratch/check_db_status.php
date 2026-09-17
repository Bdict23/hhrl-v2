<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;
use App\Models\DataManagement\Price;
use App\Models\DataManagement\Recipe;

echo "Total items: " . Item::count() . PHP_EOL;
echo "Total prices: " . Price::count() . PHP_EOL;
echo "Price types: " . json_encode(Price::distinct()->pluck('price_type')) . PHP_EOL;
echo "Total recipes: " . Recipe::count() . PHP_EOL;

$itemsWithPrices = Item::whereHas('cost')->count();
echo "Items with cost: " . $itemsWithPrices . PHP_EOL;

$recipesWithIng = Recipe::whereHas('ingredients')->count();
echo "Recipes with ingredients: " . $recipesWithIng . PHP_EOL;
