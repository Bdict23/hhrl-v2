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
        Schema::create('revolving_funds', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('CASCADE');
            $table->foreignId('prepared_by')->constrained('employees')->onDelete('CASCADE');
            $table->enum('status', ['DRAFT', 'OPEN', 'CLOSED', 'CANCELLED'])->default('OPEN');
            $table->decimal('replenished_amount', 15, 2);
            $table->decimal('ceiling_amount', 15, 2);
            $table->decimal('starting_balance', 15, 2)->default(0);
            $table->decimal('ending_balance', 15, 2)->default(0);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('acknowledgement_id')->nullable()->constrained('acknowledgement_receipts')->onDelete('SET NULL');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revolving_funds');
    }
};
