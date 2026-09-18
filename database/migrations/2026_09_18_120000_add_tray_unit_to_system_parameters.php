<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Settings\SystemParameter;
use App\Models\DataManagement\UnitOfMeasure;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Find reference parameter to copy module_id, branch_id, created_by
        $unitParam = SystemParameter::where('key', 'UNIT')->first()
            ?? SystemParameter::where('key', 'measure_type')->first();
        $moduleId = $unitParam?->module_id ?? 25;
        $branchId = $unitParam?->branch_id ?? 1;
        $createdBy = $unitParam?->created_by ?? 1;

        $unitTypeParam = SystemParameter::where('key', 'measure_type')
            ->where('name', 'UNIT')
            ->first();

        // 2. Add Tray under key = 'UNIT' so it appears in the measuredSymbol dropdown
        SystemParameter::firstOrCreate(
            [
                'key'  => 'UNIT',
                'name' => 'Tray',
            ],
            [
                'module_id'   => $moduleId,
                'branch_id'   => $branchId,
                'description' => 'Tray (24 pcs)',
                'created_by'  => $createdBy,
                'status'      => 'ACTIVE',
                'sequence'    => 5,
            ]
        );

        // 3. Add Tray under key = 'measure_symbol'
        SystemParameter::firstOrCreate(
            [
                'key'  => 'measure_symbol',
                'name' => 'Tray',
            ],
            [
                'module_id'   => $moduleId,
                'branch_id'   => $branchId,
                'description' => 'Tray (24 pcs)',
                'created_by'  => $createdBy,
                'status'      => 'ACTIVE',
                'sequence'    => 5,
            ]
        );

        // 4. Ensure UnitOfMeasure record exists for Tray with ratio/value 24
        UnitOfMeasure::updateOrCreate(
            [
                'unit_symbol' => 'tray',
            ],
            [
                'unit_name'        => 'Tray (24 pcs)',
                'unit_description' => 'Tray of 24 pieces',
                'measure_symbol'   => 'tray',
                'measure_value'    => 24.0,
                'measure_type_id'  => $unitTypeParam?->id,
                'status'           => 'ACTIVE',
                'company_id'       => 1,
                'created_by'       => $createdBy,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SystemParameter::where('name', 'Tray')->whereIn('key', ['UNIT', 'measure_symbol'])->delete();
        UnitOfMeasure::where('unit_symbol', 'tray')->delete();
    }
};
