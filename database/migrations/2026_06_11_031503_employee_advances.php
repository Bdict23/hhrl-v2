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
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('CASCADE');
            $table->foreignId('prepared_by')->constrained('employees')->onDelete('CASCADE');
            $table->foreignId('received_by')->constrained('employees')->onDelete('CASCADE');
            $table->foreignId('approved_by')->constrained('employees')->onDelete('CASCADE');
            $table->enum('status', ['DRAFT', 'CLOSED', 'CANCELLED', 'OPEN', 'FOR DISBURSEMENT', 'FOR APPROVAL', 'REJECTED'])->default('DRAFT');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('employee_advances_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('employee_advances')->onDelete('cascade');
            $table->enum('type', ['IN', 'OUT'])->default('IN');
            $table->enum('status', ['DRAFT', 'FINAL', 'CANCELLED'])->default('FINAL');
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->foreignId('pcv_id')->nullable()->constrained('petty_cash_vouchers')->onDelete('cascade');
            $table->foreignId('cash_return_id')->nullable()->constrained('cash_returns')->onDelete('cascade');
            $table->foreignId('revolving_fund_id')->nullable()->constrained('revolving_funds')->onDelete('cascade');
            $table->timestamps();
        });
        Schema::table('petty_cash_vouchers', function (Blueprint $table) {
            $table->foreignId('employee_advance_id')->nullable()->constrained('employee_advances')->onDelete('cascade');
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
