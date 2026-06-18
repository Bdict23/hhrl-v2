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
        Schema::table('advance_liquidation_snaptshots', function (Blueprint $table) {
            $table->foreignId('reimbursement_id')->nullable()->constrained('reimbursements')->onDelete('cascade');
        });
        Schema::table('revolving_fund_snapshots', function (Blueprint $table) {
            $table->foreignId('reimbursement_id')->nullable()->constrained('reimbursements')->onDelete('cascade');
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
