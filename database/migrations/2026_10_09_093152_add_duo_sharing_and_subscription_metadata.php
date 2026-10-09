<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->boolean('shares_with_partner')->default(false);
        });
        Schema::table('purchases', function (Blueprint $table): void {
            $table->boolean('is_trial')->default(false);
            $table->boolean('auto_renews')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn(['is_trial', 'auto_renews']);
        });
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('shares_with_partner');
        });
    }
};
