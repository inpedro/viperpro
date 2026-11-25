<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline for tables the application uses but that existed only in sql/viperpro.sql,
 * so `php artisan migrate` on an empty database failed. Every block is guarded with
 * hasTable(): on installations created from the SQL dump this migration is a no-op.
 * Column definitions mirror the dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Referenced by 2023_10_08_111755_add_fields_to_game_exclusives_table but never created; no model uses it.
        if (! Schema::hasTable('game_exclusives')) {
            Schema::create('game_exclusives', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ggds_spin_config')) {
            Schema::create('ggds_spin_config', function (Blueprint $table) {
                $table->id();
                $table->text('prizes');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ggds_spin_runs')) {
            Schema::create('ggds_spin_runs', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('nonce');
                $table->text('possibilities');
                $table->string('prize', 191);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('suit_pay_payments')) {
            Schema::create('suit_pay_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_id', 191)->nullable();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('withdrawal_id')->index();
                $table->string('pix_key', 191)->nullable();
                $table->string('pix_type', 50)->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->text('observation')->nullable();
                $table->tinyInteger('status')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: these tables may predate this migration (SQL-dump installs).
    }
};
