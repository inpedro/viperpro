<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->string('version', 40);
            $table->string('path', 255);
            $table->boolean('enabled')->default(false);
            $table->text('config')->nullable();          // encrypted JSON (APP_KEY)
            $table->char('lock_hash', 64)->nullable();   // sha256 of addon.lock at install/enable
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });

        // General-purpose administrative audit trail (append-only by convention; Phase 8 adds UI).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 191)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
        Schema::dropIfExists('audit_logs');
    }
};
