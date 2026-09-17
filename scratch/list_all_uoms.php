<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DataManagement\UnitOfMeasure;
use App\Models\SystemParameter;

$uoms = UnitOfMeasure::all(['id', 'measure_type_id', 'unit_name', 'unit_symbol', 'measure_symbol', 'measure_value']);
foreach ($uoms as $u) {
    $param = $u->measure_type_id ? SystemParameter::find($u->measure_type_id)?->name : null;
    echo "ID: {$u->id} | Param: {$param} ({$u->measure_type_id}) | Name: {$u->unit_name} | UnitSym: {$u->unit_symbol} | MeasureSym: {$u->measure_symbol} | Val: {$u->measure_value}\n";
}
