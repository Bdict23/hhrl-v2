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
        Schema::create('pcv_liquidation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pcv_id')->constrained('petty_cash_vouchers')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->timestamp('purchase_date')->useCurrent();
            $table->string('vendor')->nullable();
            $table->string('reference')->nullable();
            $table->string('particular')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
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
