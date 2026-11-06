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
        Schema::create('event_menu_package_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_menu_id')->constrained('event_menus')->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('package_code', 50);
            $table->string('package_name');
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->timestamps();

            $table->index(['event_menu_id', 'recipe_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_menu_package_recipes');
    }
};
