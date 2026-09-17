<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;
use App\Services\DataManagement\UnitConversionService;

$service = app(UnitConversionService::class);
$porkKasim = Item::find(5192);

echo "Pork Kasim (ID 5192):\n";
echo "Description: " . $porkKasim->item_description . "\n";
echo "measurement_type: " . $porkKasim->measurement_type . "\n";
print_r($service->getCompatibleUnitsForItem($porkKasim)->toArray());
