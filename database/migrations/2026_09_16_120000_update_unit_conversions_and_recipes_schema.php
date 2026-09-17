<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations -ag.
     */
    public function up(): void
    {
        // 1. Update unit_conversions conversion_factor precision if needed
        if (Schema::hasTable('unit_conversions')) {
            Schema::table('unit_conversions', function (Blueprint $table) {
                if (!Schema::hasColumn('unit_conversions', 'item_id')) {
                    $table->unsignedBigInteger('item_id')->nullable()->after('id');
                }
                // Ensure conversion_factor has high precision
                $table->decimal('conversion_factor', 24, 8)->change();
            });
        }

        // 2. Update recipes table (Ingredients)
        if (Schema::hasTable('recipes')) {
            Schema::table('recipes', function (Blueprint $table) {
                if (!Schema::hasColumn('recipes', 'cost')) {
                    $table->decimal('cost', 15, 4)->nullable()->after('qty');
                }
                // Make price_level_id nullable if it was not nullable
                if (Schema::hasColumn('recipes', 'price_level_id')) {
                    $table->unsignedBigInteger('price_level_id')->nullable()->change();
                }
            });
        }

        // 3. Update menus table (Recipes)
        if (Schema::hasTable('menus')) {
            Schema::table('menus', function (Blueprint $table) {
                if (!Schema::hasColumn('menus', 'total_cost')) {
                    $table->decimal('total_cost', 15, 2)->default(0.00)->after('recipe_type');
                }
                if (!Schema::hasColumn('menus', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approver_id');
                }
                if (!Schema::hasColumn('menus', 'serving_size')) {
                    $table->decimal('serving_size', 8, 2)->default(1.00)->after('total_cost');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('menus')) {
            Schema::table('menus', function (Blueprint $table) {
                if (Schema::hasColumn('menus', 'serving_size')) {
                    $table->dropColumn('serving_size');
                }
                if (Schema::hasColumn('menus', 'total_cost')) {
                    $table->dropColumn('total_cost');
                }
                if (Schema::hasColumn('menus', 'approved_by')) {
                    $table->dropColumn('approved_by');
                }
            });
        }

        if (Schema::hasTable('recipes')) {
            Schema::table('recipes', function (Blueprint $table) {
                if (Schema::hasColumn('recipes', 'cost')) {
                    $table->dropColumn('cost');
                }
            });
        }
    }
};
