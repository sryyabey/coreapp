<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table): void {
            $table->string('support_api_key_hash', 64)->nullable();
        });
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('external_user_id', 128)->nullable();
            $table->string('external_user_name', 160)->nullable();
            $table->string('external_user_email')->nullable();
            $table->unique(['app_id', 'external_user_id', 'client_request_id'], 'support_external_request_unique');
            $table->index(['app_id', 'external_user_id', 'updated_at'], 'support_external_inbox_index');
        });
    }

    public function down(): void
    {
        if (DB::table('support_tickets')->whereNotNull('external_user_id')->exists()) {
            throw new RuntimeException('Dış destek talepleri varken bu migration geri alınamaz.');
        }
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropUnique('support_external_request_unique');
            $table->dropIndex('support_external_inbox_index');
            $table->dropColumn(['external_user_id', 'external_user_name', 'external_user_email']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::table('apps', function (Blueprint $table): void {
            $table->dropColumn('support_api_key_hash');
        });
    }
};
