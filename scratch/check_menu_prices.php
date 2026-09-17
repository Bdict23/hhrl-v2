<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\Price;

$rows = Price::whereNotNull('menu_id')->get(['id', 'menu_id', 'price_type', 'amount', 'created_at']);
echo "Count of price_levels with menu_id: " . $rows->count() . "\n";
foreach ($rows->take(10) as $r) {
    echo "ID: {$r->id} | Menu ID: {$r->menu_id} | Type: {$r->price_type} | Amount: {$r->amount} | Created: {$r->created_at}\n";
}
