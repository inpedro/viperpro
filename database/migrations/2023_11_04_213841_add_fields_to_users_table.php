<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oauth_id')->nullable();
            $table->string('oauth_type')->nullable();
            // avatar is already created by 2014_10_12_000000_create_users_table (duplicate removed: broke fresh installs)
            $table->string('last_name')->nullable();
            $table->string('cpf', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->tinyInteger('logged_in')->default(0);
            $table->tinyInteger('banned')->default(0);
            $table->integer('inviter')->nullable();
            $table->string('inviter_code', 25)->nullable();
            $table->bigInteger('affiliate_revenue_share')->default(2);
            $table->decimal('affiliate_cpa', 20, 2)->default(10);
            $table->decimal('affiliate_baseline', 20, 2)->default(40);
            $table->tinyInteger('is_demo_agent')->default(0);
            $table->string('status', 50)->default('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['oauth_id', 'oauth_type', 'last_name', 'cpf', 'phone', 'logged_in', 'banned', 'inviter', 'inviter_code', 'affiliate_revenue_share', 'affiliate_cpa', 'affiliate_baseline', 'is_demo_agent', 'status']);
        });
    }
};
