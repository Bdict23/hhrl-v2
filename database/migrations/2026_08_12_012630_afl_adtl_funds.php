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
        Schema::create('afl_adtl_funds', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();
            $table->foreignId('advances_liquidation_id')->nullable()->constrained('advance_liquidations')->onDelete('cascade');
            $table->decimal('amount')->default(0.00);
            $table->foreignId('prepared_by')->constrained('employees')->onDelete('CASCADE');
            $table->enum('status', ['FINAL', 'CANCELLED'])->default('FINAL');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::table('advance_liquidation_snaptshots', function (Blueprint $table) {
            $table->foreignId('adtl_fund_id')->nullable()->constrained('afl_adtl_funds')->onDelete('cascade');
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
