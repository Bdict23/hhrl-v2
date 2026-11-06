<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_menu_package_recipes', function (Blueprint $table) {
            $table->foreignId('recipe_package_id')
                ->nullable()
                ->after('event_menu_id')
                ->constrained('recipe_packages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_menu_package_recipes', function (Blueprint $table) {
            $table->dropForeign(['recipe_package_id']);
            $table->dropColumn('recipe_package_id');
        });
    }
};
