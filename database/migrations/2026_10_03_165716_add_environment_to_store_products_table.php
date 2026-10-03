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
        Schema::table('store_products', function (Blueprint $table) {
            $table->index('store_app_id', 'store_products_store_app_index');
            $table->dropUnique('store_products_store_identity_unique');
            $table->enum('environment', ['production', 'sandbox'])->default('production');
            $table->unique(['store_app_id', 'environment', 'product_id', 'base_plan_id'], 'store_products_store_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->dropUnique('store_products_store_identity_unique');
            $table->dropColumn('environment');
            $table->unique(['store_app_id', 'product_id', 'base_plan_id'], 'store_products_store_identity_unique');
            $table->dropIndex('store_products_store_app_index');
        });
    }
};
