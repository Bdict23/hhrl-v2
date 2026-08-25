<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('unit_of_measures', function (Blueprint $table) {
            $table->foreignId('measure_type_id')->nullable()->after('unit_symbol')->constrained('system_parameters')->onDelete('set null');
            $table->decimal('measure_value', 15, 2)->after('measure_type_id')->default(0);
            $table->string('measure_symbol')->after('measure_value')->nullable();

            $table->index('measure_value', 'unit_of_measures_measure_value_index');
            $table->index('measure_symbol', 'unit_of_measures_measure_symbol_index');
        });
        Schema::table('system_parameters', function (Blueprint $table) {
            $table->integer('sequence')->default(0);
            $table->index('sequence', 'sequence_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
