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
        Schema::create('shiftcal_wage_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('overtime_multiplier', 5, 2)->default(1.5);
            $table->char('currency', 3)->default('TRY');
            $table->unsignedInteger('weekly_target_minutes')->default(2400);
            $table->unique(['app_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shiftcal_wage_settings');
    }
};
