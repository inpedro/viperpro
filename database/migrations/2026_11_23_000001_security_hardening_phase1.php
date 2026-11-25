<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 security hardening. See docs/SECURITY_AUDIT.md.
 *
 *  1. Audit log of every inbound payment / game-provider callback.
 *  2. Financial tables converted from MyISAM to InnoDB (MySQL only): MyISAM has no
 *     transactions and no row locks, so DB::transaction()/lockForUpdate() were no-ops.
 *  3. Unique index on transactions.payment_id (only if no duplicates exist yet).
 *  4. Single-use spin-wheel redemptions.
 *  5. Removes the anonymous AWS Lambda "DigitoPay" endpoint shipped as default data.
 */
return new class extends Migration
{
    private array $financialTables = [
        'wallets', 'transactions', 'deposits', 'withdrawals', 'affiliate_withdraws',
        'affiliate_histories', 'wallet_changes', 'system_wallets', 'orders',
        'suit_pay_payments', 'digito_pay_payments', 'users', 'ggds_spin_runs', 'password_reset_tokens',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('callback_logs')) {
            Schema::create('callback_logs', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 20);          // payment | provider
                $table->string('source', 50);           // suitpay, digitopay, fivers, ...
                $table->string('ip', 45)->nullable();
                $table->string('outcome', 30);          // accepted | rejected | ignored | error
                $table->string('reason', 191)->nullable();
                $table->string('reference', 191)->nullable();
                $table->char('payload_sha256', 64);
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['source', 'created_at']);
                $table->index('reference');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            foreach ($this->financialTables as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
                }
            }
        }

        if (Schema::hasTable('transactions')) {
            $duplicates = DB::table('transactions')
                ->select('payment_id')->groupBy('payment_id')->havingRaw('COUNT(*) > 1')->count();

            if ($duplicates === 0) {
                Schema::table('transactions', fn (Blueprint $t) => $t->unique('payment_id', 'transactions_payment_id_unique'));
            } else {
                Log::warning("security migration: transactions.payment_id has {$duplicates} duplicated values; unique index NOT created. Reconcile manually.");
            }
        }

        if (Schema::hasTable('ggds_spin_runs') && ! Schema::hasColumn('ggds_spin_runs', 'redeemed_at')) {
            Schema::table('ggds_spin_runs', function (Blueprint $t) {
                $t->timestamp('redeemed_at')->nullable();
                $t->unsignedBigInteger('redeemed_by')->nullable();
            });
        }

        if (Schema::hasTable('gateways') && Schema::hasColumn('gateways', 'digitopay_uri')) {
            DB::table('gateways')->where('digitopay_uri', 'like', '%lambda-url%')->update(['digitopay_uri' => null]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_logs');

        if (Schema::hasTable('ggds_spin_runs') && Schema::hasColumn('ggds_spin_runs', 'redeemed_at')) {
            Schema::table('ggds_spin_runs', fn (Blueprint $t) => $t->dropColumn(['redeemed_at', 'redeemed_by']));
        }

        if (Schema::hasTable('transactions')) {
            try {
                Schema::table('transactions', fn (Blueprint $t) => $t->dropUnique('transactions_payment_id_unique'));
            } catch (\Throwable) {
                // index was never created
            }
        }
        // Engine conversion and the removed Lambda URL are intentionally not reverted.
    }
};
