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
        Schema::create('shiftcal_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 100);
            $table->string('color', 7);
            $table->text('note')->nullable();
            $table->index(['app_id', 'user_id', 'starts_at']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shiftcal_events');
    }
};
