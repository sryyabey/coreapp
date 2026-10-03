<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shiftcal_events', function (Blueprint $table): void {
            $table->uuid('client_request_id')->nullable();
            $table->unique(['app_id', 'user_id', 'client_request_id'], 'shiftcal_events_client_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shiftcal_events', function (Blueprint $table): void {
            $table->dropUnique('shiftcal_events_client_request_unique');
            $table->dropColumn('client_request_id');
        });
    }
};
