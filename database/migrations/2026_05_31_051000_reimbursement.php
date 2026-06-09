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
       Schema::create('reimbursements', function (Blueprint $table) {
        $table->id();
        $table->string('reference')->nullable();
        $table->enum('status', ['DRAFT','FOR APPROVAL','REJECTED','CLOSED', 'CANCELLED'])->default('DRAFT');
        $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
        $table->foreignId('pcv_id')->constrained('petty_cash_vouchers')->onDelete('cascade');
        $table->decimal('amount')->default(0);
        $table->foreignId('prepared_by')->constrained('employees')->onDelete('cascade');
        $table->foreignId('approved_by')->constrained('employees')->onDelete('cascade');
        $table->timestamp('approved_date')->nullable();
        $table->timestamp('rejected_date')->nullable();
        $table->string('note')->nullable();
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
