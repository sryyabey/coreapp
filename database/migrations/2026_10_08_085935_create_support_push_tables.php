<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $table): void {
            $table->boolean('support_replies')->default(true);
        });
        Schema::create('support_push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained('devices')->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('token');
            $table->char('token_hash', 64);
            $table->string('locale', 2)->default('tr');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['app_id', 'token_hash']);
        });
        Schema::create('support_push_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->unique()->constrained('support_messages')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
        Schema::create('support_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('outbox_id')->constrained('support_push_outbox')->cascadeOnDelete();
            $table->foreignId('push_device_id')->constrained('support_push_devices')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 50)->nullable();
            $table->string('provider_id')->nullable();
            $table->timestamps();
            $table->unique(['outbox_id', 'push_device_id']);
        });
        DB::table('support_push_devices')->insertUsing(['device_id', 'app_id', 'user_id', 'token', 'token_hash', 'locale', 'enabled', 'created_at', 'updated_at'],
            DB::table('shiftcal_push_devices')->select(['device_id', 'app_id', 'user_id', 'token', 'token_hash', 'locale', 'enabled', 'created_at', 'updated_at']));
    }

    public function down(): void
    {
        Schema::dropIfExists('support_push_deliveries');
        Schema::dropIfExists('support_push_outbox');
        Schema::dropIfExists('support_push_devices');
        Schema::table('app_users', function (Blueprint $table): void {
            $table->dropColumn('support_replies');
        });
    }
};
