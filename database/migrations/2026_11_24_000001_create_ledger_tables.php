<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only double-entry ledger (Phase 2). See docs/LEDGER.md.
 *
 *  ledger_accounts  one row per (wallet, bucket) and per (platform account, currency)
 *  ledger_journals  one business event (deposit, bet, win, ...); immutable
 *  ledger_entries   signed postings in integer minor units; entries of a journal sum to 0
 *
 * Wallet balance columns remain as a projection that must always equal the sum of
 * the corresponding account's entries (php artisan wallet:reconcile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();          // wallet:12:balance | platform:deposits:EUR
            $table->string('kind', 20);                     // player | platform
            $table->unsignedBigInteger('wallet_id')->nullable()->index();
            $table->string('bucket', 40)->nullable();       // balance, balance_bonus, balance_withdrawal, refer_rewards
            $table->string('currency', 10);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ledger_journals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 60)->index();            // deposit, withdrawal.request, game.bet, ...
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('reference_type', 60)->nullable();
            $table->string('reference_id', 191)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable(); // admin/user who caused it, if any
            $table->string('source', 30)->default('service');   // service | observed (legacy call site)
            $table->string('description', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('ledger_journals');
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->bigInteger('amount_minor');             // signed: + credit to the account, - debit
            $table->string('currency', 10);
            $table->bigInteger('balance_after_minor')->nullable(); // running balance for player accounts
            $table->timestamp('created_at')->useCurrent();
            $table->index(['account_id', 'id']);
        });

        if (DB::getDriverName() === 'mysql') {
            foreach (['ledger_accounts', 'ledger_journals', 'ledger_entries'] as $t) {
                DB::statement("ALTER TABLE `{$t}` ENGINE=InnoDB");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_journals');
        Schema::dropIfExists('ledger_accounts');
    }
};
