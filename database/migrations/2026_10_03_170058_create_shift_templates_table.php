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
        Schema::create('shiftcal_shift_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->unsignedTinyInteger('end_day_offset')->default(0);
            $table->string('color', 7);
            $table->text('note')->nullable();
            $table->index(['app_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shiftcal_shift_templates');
    }
};
