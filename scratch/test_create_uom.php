<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\UnitOfMeasure;
use App\Models\Settings\SystemParameter;

$param = SystemParameter::where('key', 'measure_type')->where('name', 'WEIGHT')->first();
echo "Param: " . ($param ? $param->id : 'none') . "\n";

$uom = UnitOfMeasure::firstOrCreate(
    ['unit_symbol' => 'lbs'],
    [
        'unit_name'       => 'Pounds',
        'measure_symbol'  => 'lbs',
        'measure_type_id' => $param?->id,
        'measure_value'   => 1.0,
        'status'          => 'ACTIVE',
        'company_id'      => 1,
    ]
);
echo "LBS ID: " . $uom->id . "\n";
