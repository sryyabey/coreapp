<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreign(['app_id', 'user_id'])->references(['app_id', 'user_id'])->on('app_users')->cascadeOnDelete();
            $table->uuid('client_request_id');
            $table->string('subject', 160);
            $table->string('status', 20)->default('open');
            $table->string('locale', 10)->default('tr');
            $table->timestamp('user_read_at', 6)->nullable();
            $table->timestamp('last_staff_reply_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['app_id', 'user_id', 'client_request_id']);
            $table->index(['app_id', 'user_id', 'updated_at']);
            $table->index(['status', 'updated_at']);
        });
        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 20);
            $table->uuid('client_request_id');
            $table->text('body');
            $table->timestamps(6);
            $table->unique(['support_ticket_id', 'sender_type', 'client_request_id'], 'support_message_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
