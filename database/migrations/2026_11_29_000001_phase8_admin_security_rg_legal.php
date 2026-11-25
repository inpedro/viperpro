<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8: admin MFA + session tracking, token revocation, responsible gaming, legal pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable();            // encrypted (APP_KEY)
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->text('mfa_recovery_codes')->nullable();    // encrypted JSON of hashes
            $table->unsignedBigInteger('mfa_last_step')->nullable(); // TOTP replay protection
            $table->timestamp('tokens_valid_after')->nullable(); // JWTs issued before this are rejected
        });

        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->char('session_hash', 64)->unique();
            $table->string('panel', 20);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean('mfa_verified')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('responsible_gaming_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->unique();
            foreach (['daily', 'weekly', 'monthly'] as $p) {
                $table->decimal("deposit_limit_{$p}", 20, 2)->nullable();
                $table->decimal("pending_deposit_limit_{$p}", 20, 2)->nullable();
            }
            $table->timestamp('pending_limits_effective_at')->nullable(); // increases wait (cooling-off)
            $table->timestamp('excluded_until')->nullable();              // cool-off / self-exclusion end
            $table->boolean('excluded_permanently')->default(false);
            $table->unsignedSmallInteger('reality_check_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('slug', 60);
            $table->string('locale', 10);
            $table->string('title', 191);
            $table->longText('body_markdown');
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug', 'locale']);
        });

        if (DB::getDriverName() === 'mysql') {
            foreach (['admin_sessions', 'responsible_gaming_settings', 'legal_pages'] as $t) {
                DB::statement("ALTER TABLE `{$t}` ENGINE=InnoDB");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
        Schema::dropIfExists('responsible_gaming_settings');
        Schema::dropIfExists('admin_sessions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['mfa_secret', 'mfa_enabled_at', 'mfa_recovery_codes', 'mfa_last_step', 'tokens_valid_after']));
    }
};
