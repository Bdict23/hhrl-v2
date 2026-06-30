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
        DB::statement("ALTER TABLE event_liquidations MODIFY COLUMN status 
            ENUM('DRAFT','FOR REVIEW','FOR SETTLEMENT','FOR APPROVAL','CLOSED','CANCELLED') 
            NOT NULL DEFAULT 'DRAFT'");
    }



    public function down(): void
    {
        //
    }
};
