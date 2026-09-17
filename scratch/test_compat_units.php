<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;
use App\Services\DataManagement\UnitConversionService;

$service = app(UnitConversionService::class);
$pork = Item::where('item_description', 'like', '%pork%')->first();
$oil = Item::where('item_description', 'like', '%oil%')->first();

echo "Pork (" . $pork->item_description . ") Units:\n";
print_r($service->getCompatibleUnitsForItem($pork)->toArray());

echo "Oil (" . $oil->item_description . ") Units:\n";
print_r($service->getCompatibleUnitsForItem($oil)->toArray());
