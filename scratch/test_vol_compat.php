<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;
use App\Services\DataManagement\UnitConversionService;

$service = app(UnitConversionService::class);
$oil = Item::where('item_description', 'like', '%oil%')->where('measurement_type', 'VOLUME')->first();
if (!$oil) {
    $oil = Item::where('item_description', 'like', '%oil%')->first();
    $oil->measurement_type = 'VOLUME';
}

echo "Oil: " . $oil->item_description . " (Type: " . $oil->measurement_type . ")\n";
print_r($service->getCompatibleUnitsForItem($oil)->toArray());
