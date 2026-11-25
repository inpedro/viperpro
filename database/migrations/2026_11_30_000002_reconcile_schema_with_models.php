<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columns and a table that exist in the original SQL dump (and are used by the admin forms and
 * providers) but were never created by a migration, so installs built from migrations failed
 * when those forms were saved. Each change is skipped where it already exists (dump installs).
 * Guarded by FillableColumnsExistTest.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'software_background'   => fn (Blueprint $t) => $t->string('software_background')->nullable(),
            'revshare_reverse'      => fn (Blueprint $t) => $t->boolean('revshare_reverse')->nullable()->default(true), // dump default
            'turn_on_football'      => fn (Blueprint $t) => $t->boolean('turn_on_football')->nullable()->default(false),
            'sharkpay_is_enable'    => fn (Blueprint $t) => $t->boolean('sharkpay_is_enable')->nullable()->default(false),
            'mercadopago_is_enable' => fn (Blueprint $t) => $t->boolean('mercadopago_is_enable')->nullable()->default(false),
        ];
        foreach ($settings as $column => $add) {
            if (! Schema::hasColumn('settings', $column)) {
                Schema::table('settings', $add);
            }
        }

        if (! Schema::hasColumn('providers', 'distribution')) {
            Schema::table('providers', fn (Blueprint $t) => $t->string('distribution', 50)->nullable());
        }

        if (! Schema::hasTable('ggr_games')) {
            Schema::create('ggr_games', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedInteger('user_id')->index();
                $table->string('provider', 191);
                $table->string('game', 191);
                $table->decimal('balance_bet', 20, 2)->default(0);
                $table->decimal('balance_win', 20, 2)->default(0);
                $table->string('currency', 50)->nullable();
                $table->string('aggregator')->nullable();
                $table->string('type', 20)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Only remove what cannot hold data that predates this migration.
        if (Schema::hasTable('ggr_games') && DB::table('ggr_games')->count() === 0) {
            Schema::drop('ggr_games');
        }
        // Added columns are kept: on dump installs they predate this migration.
    }
};
