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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_app_id')->constrained('store_apps')->restrictOnDelete();
            $table->foreignId('store_product_id')->constrained('store_products')->restrictOnDelete();
            $table->string('environment', 20);
            $table->string('identity', 255);
            $table->text('proof');
            $table->string('status', 30);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->unique(['store_app_id', 'environment', 'identity'], 'purchases_store_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
