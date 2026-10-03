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
        Schema::create('store_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_app_id')->constrained('store_apps')->restrictOnDelete();
            $table->string('event_id', 100);
            $table->string('type', 100);
            $table->string('environment', 20);
            $table->string('identity')->nullable();
            $table->text('proof')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->unique(['store_app_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_notifications');
    }
};
