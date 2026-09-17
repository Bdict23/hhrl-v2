<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;
use App\Services\DataManagement\UnitConversionService;

$service = app(UnitConversionService::class);
$porkKasim = Item::find(5192);

// Mock cost to 320.00 if needed for test
$calc = $service->calculateIngredientCost($porkKasim, 250, 28);
print_r($calc);
