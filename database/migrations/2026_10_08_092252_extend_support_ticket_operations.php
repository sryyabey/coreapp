<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal');
            $table->string('category', 30)->default('general');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_customer_message_at', 6)->nullable();
            $table->timestamp('closed_at', 6)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'due_at']);
            $table->index(['app_id', 'status', 'priority']);
        });
        DB::table('support_tickets')->update(['last_customer_message_at' => DB::raw("COALESCE((SELECT MAX(support_messages.created_at) FROM support_messages WHERE support_messages.support_ticket_id = support_tickets.id AND support_messages.sender_type = 'user'), created_at)")]);
        DB::table('support_tickets')->where('status', 'closed')->update(['closed_at' => DB::raw('updated_at')]);
        Schema::create('support_agent_apps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['app_id', 'user_id']);
        });
        Schema::create('support_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestamps(6);
            $table->unique(['support_ticket_id', 'request_id']);
        });
        Schema::create('support_reply_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_id')->nullable()->constrained('apps')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('locale', 2)->default('tr');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['app_id', 'locale', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_reply_templates');
        Schema::dropIfExists('support_activities');
        Schema::dropIfExists('support_agent_apps');
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['status', 'due_at']);
            $table->dropIndex(['app_id', 'status', 'priority']);
            $table->dropColumn(['assigned_to', 'priority', 'category', 'due_at', 'last_customer_message_at', 'closed_at', 'version']);
        });
    }
};
