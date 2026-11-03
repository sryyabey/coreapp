<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_guest_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->uuid('external_user_id');
            $table->string('token_hash', 64);
            $table->timestamps();
            $table->unique(['app_id', 'external_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_guest_sessions');
    }
};
