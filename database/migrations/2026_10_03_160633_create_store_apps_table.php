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
        Schema::create('store_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->enum('platform', ['ios', 'android']);
            $table->string('identifier');
            $table->string('apple_app_id', 20)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['app_id', 'platform']);
            $table->unique(['platform', 'identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_apps');
    }
};
