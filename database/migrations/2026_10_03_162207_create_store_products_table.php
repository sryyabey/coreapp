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
        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('store_app_id')->constrained('store_apps')->restrictOnDelete();
            $table->string('product_id');
            $table->string('base_plan_id')->default('');
            $table->boolean('is_active')->default(true);
            $table->unique(['store_app_id', 'product_id', 'base_plan_id'], 'store_products_store_identity_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_products');
    }
};
