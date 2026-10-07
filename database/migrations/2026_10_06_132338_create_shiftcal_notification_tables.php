<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiftcal_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('shared_updates')->default(true);
            $table->boolean('shared_cancellations')->default(true);
            $table->boolean('show_details')->default(false);
            foreach (['work', 'duty', 'plan'] as $type) {
                $table->boolean($type.'_enabled')->default(false);
                $table->unsignedTinyInteger($type.'_minutes')->default(15);
            }
            $table->timestamps();
            $table->unique(['app_id', 'user_id']);
        });
        Schema::create('shiftcal_push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained('devices')->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->char('token_hash', 64);
            $table->string('locale', 2)->default('tr');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['app_id', 'token_hash']);
        });
        Schema::create('shiftcal_push_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 180);
            $table->string('type', 30);
            $table->uuid('plan_id')->nullable();
            $table->uuid('connection_id')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['app_id', 'user_id', 'event_key']);
            $table->index(['status', 'due_at']);
        });
        Schema::create('shiftcal_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('outbox_id');
            $table->foreign('outbox_id')->references('id')->on('shiftcal_push_outbox')->cascadeOnDelete();
            $table->foreignId('push_device_id')->constrained('shiftcal_push_devices')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 50)->nullable();
            $table->string('provider_id')->nullable();
            $table->timestamps();
            $table->unique(['outbox_id', 'push_device_id']);
        });
    }

    public function down(): void
    {
        foreach (['shiftcal_push_deliveries', 'shiftcal_push_outbox', 'shiftcal_push_devices', 'shiftcal_notification_preferences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
