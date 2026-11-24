<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theme selection per tenant (Phase 6). NULL = legacy mode: the tenant's existing
 * custom_layouts colours keep driving the UI exactly as before, until an admin picks a theme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_layouts', function (Blueprint $table) {
            $table->string('theme', 30)->nullable();
            $table->json('theme_overrides')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('custom_layouts', fn (Blueprint $table) => $table->dropColumn(['theme', 'theme_overrides']));
    }
};
