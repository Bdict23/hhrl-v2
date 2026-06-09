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
       Schema::create('advance_liquidation_snaptshots', function (Blueprint $table) {
        $table->id();
        $table->foreignId('advance_liquidation_id')->constrained('advance_liquidations')->onDelete('cascade');
        $table->enum('type', ['IN', 'OUT'])->default('IN');
        $table->enum('status', ['DRAFT', 'FINAL','CANCELLED'])->default('FINAL');
        $table->string('description')->nullable();
        $table->foreignId('pcv_id')->nullable()->constrained('petty_cash_vouchers')->onDelete('cascade');
        $table->foreignId('cash_return_id')->nullable()->constrained('cash_returns')->onDelete('cascade');
        $table->decimal('amount', 15, 2)->default(0);
        $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
        $table->decimal('balance', 15, 2)->default(0);
        $table->timestamps();
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
