<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiftcal_partner_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['app_id', 'user_id']);
        });
        Schema::create('shiftcal_partner_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('connection_id')->index();
            $table->timestamps();
            $table->unique(['app_id', 'user_id']);
            $table->unique(['app_id', 'partner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiftcal_partner_links');
        Schema::dropIfExists('shiftcal_partner_invitations');
    }
};
