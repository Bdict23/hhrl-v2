<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Item;

$pork = Item::where('item_description', 'like', '%pork%')->first();
echo "Pork:\n";
echo "ID: " . $pork->id . "\n";
echo "item_description: " . $pork->item_description . "\n";
echo "measurement_type: " . var_export($pork->measurement_type, true) . "\n";
echo "unit_id: " . $pork->unit_id . "\n";
if ($pork->unit) {
    echo "Unit name: " . $pork->unit->unit_name . "\n";
    echo "Unit symbol: " . $pork->unit->unit_symbol . "\n";
    echo "Unit measure_type_id: " . $pork->unit->measure_type_id . "\n";
    echo "Unit measure_symbol: " . $pork->unit->measure_symbol . "\n";
}

$allPorks = Item::where('item_description', 'like', '%pork%')->get();
foreach ($allPorks as $p) {
    echo "Pork ID: {$p->id} | {$p->item_code} | {$p->item_description} | measurement_type: {$p->measurement_type} | unit_id: {$p->unit_id}\n";
    if ($p->unit) {
        echo "   unit: {$p->unit->unit_symbol} / {$p->unit->unit_name} / param: {$p->unit->measure_type_id}\n";
    }
}
