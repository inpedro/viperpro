<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenancy (Phase 4): shared database + tenant_id. See docs/TENANCY.md.
 *
 * All existing rows are assigned to tenant #1 ("default"), so a single-brand installation
 * behaves exactly as before. users.tenant_id is nullable: NULL = platform (super admin) user.
 */
return new class extends Migration
{
    /** Tenant-owned tables (NOT NULL tenant_id). */
    private array $tables = [
        'wallets', 'transactions', 'deposits', 'withdrawals', 'affiliate_withdraws', 'affiliate_histories',
        'orders', 'settings', 'gateways', 'games_keys', 'custom_layouts', 'banners', 'missions', 'mission_users',
        'vips', 'vip_users', 'sub_affiliates', 'ggds_spin_config', 'ggds_spin_runs', 'game_favorites', 'game_likes',
        'likes', 'game_reviews', 'suit_pay_payments', 'digito_pay_payments', 'wallet_changes', 'system_wallets',
        'setting_mails', 'ggr_games', 'ggr_games_fivers', 'ggr_games_world_slots', 'currency_alloweds',
        'ledger_accounts', 'ledger_journals', 'ledger_entries',
    ];

    /** Tables where NULL means "platform-level" row. */
    private array $nullableTables = ['users', 'callback_logs', 'audit_logs'];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->string('status', 20)->default('active'); // active | suspended
            $table->string('plan', 60)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain', 191)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        $name = Schema::hasTable('settings') ? (DB::table('settings')->value('software_name') ?: null) : null;
        DB::table('tenants')->insert([
            'id' => 1, 'slug' => 'default', 'name' => trim((string) ($name ?? config('app.name', 'Default'))) ?: 'Default',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            DB::table('tenant_domains')->insert([
                'tenant_id' => 1, 'domain' => strtolower($host), 'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ($this->tables as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'tenant_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->default(1)->index();
                });
            }
        }

        foreach ($this->nullableTables as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'tenant_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable()->index());
                DB::table($t)->update(['tenant_id' => 1]);
            }
        }

        // Uniqueness becomes per tenant.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['tenant_id', 'email']);
        });
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique('ledger_accounts_code_unique');
            $table->unique(['tenant_id', 'code']);
        });
        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->dropUnique('ledger_journals_idempotency_key_unique');
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        // password_reset_tokens has email as PRIMARY KEY, which cannot be per tenant.
        Schema::create('tenant_password_resets', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->string('email', 191);
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->primary(['tenant_id', 'email']);
        });

        if (DB::getDriverName() === 'mysql') {
            // The default only served the backfill: new rows must get tenant_id from the application.
            foreach ($this->tables as $t) {
                if (Schema::hasTable($t)) {
                    DB::statement("ALTER TABLE `{$t}` ALTER `tenant_id` DROP DEFAULT");
                }
            }
            foreach (['tenants', 'tenant_domains', 'tenant_password_resets'] as $t) {
                DB::statement("ALTER TABLE `{$t}` ENGINE=InnoDB");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_password_resets');

        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'idempotency_key']);
            $table->unique('idempotency_key');
        });
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->unique('email');
        });

        foreach (array_merge($this->tables, $this->nullableTables) as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'tenant_id')) {
                Schema::table($t, function (Blueprint $table) use ($t) {
                    $table->dropIndex("{$t}_tenant_id_index");
                    $table->dropColumn('tenant_id');
                });
            }
        }

        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
