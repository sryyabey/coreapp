<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiftcal_shared_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->uuid('connection_id')->index();
            $table->foreignId('proposer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_request_id');
            $table->string('title', 100);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 100);
            $table->string('status', 16)->default('pending');
            $table->timestamps();
            $table->unique(['app_id', 'proposer_id', 'client_request_id'], 'shared_plans_request_unique');
            $table->index(['app_id', 'connection_id', 'status', 'starts_at'], 'shared_plans_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiftcal_shared_plans');
    }
};
