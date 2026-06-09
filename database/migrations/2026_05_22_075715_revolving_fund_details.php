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
        Schema::create('revolving_fund_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revolving_fund_id')->constrained('revolving_funds')->onDelete('cascade');
            $table->enum('type', ['IN', 'OUT'])->default('IN');
            $table->enum('status', ['DRAFT', 'FINAL','CANCELLED'])->default('FINAL');
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->foreignId('pcv_id')->nullable()->constrained('petty_cash_vouchers')->onDelete('cascade');
            $table->foreignId('cash_return_id')->nullable()->constrained('cash_returns')->onDelete('cascade');
            $table->foreignId('forwarded_revolving_fund_id')->nullable()->constrained('revolving_funds');
            $table->foreignId('acknowledgement_id')->nullable()->constrained('acknowledgement_receipts');
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
