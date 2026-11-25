<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant addon activation + configuration (each brand has its own merchant credentials).
 * `addons` stays the platform-level installation ("available to tenants").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_addons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('addon_id', 80);
            $table->boolean('enabled')->default(false);
            $table->text('config')->nullable(); // encrypted JSON
            $table->timestamps();
            $table->unique(['tenant_id', 'addon_id']);
        });

        // Carry over: what was enabled/configured before tenancy belongs to tenant #1.
        foreach (DB::table('addons')->get() as $addon) {
            DB::table('tenant_addons')->insert([
                'tenant_id'  => 1,
                'addon_id'   => $addon->id,
                'enabled'    => $addon->enabled,
                'config'     => $addon->config,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `tenant_addons` ENGINE=InnoDB');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addons');
    }
};
