<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant localization and registration settings (Phase 5).
 *
 * Existing tenants keep their current behaviour: the default locale becomes the old app
 * default (pt_BR) with en/es/pt_BR enabled, and the CPF field stays as it was (optional).
 * New tenants get English, UTC and no national ID field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('default_locale', 10)->nullable();
            $table->json('enabled_locales')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('country_code', 2)->nullable();          // operator's main market, informational
            $table->string('phone_field', 10)->default('required'); // required | optional | hidden
            $table->string('document_field', 10)->default('hidden'); // required | optional | hidden
            $table->string('document_type', 20)->nullable();        // e.g. cpf (validated), passport, national_id
        });

        // users.language defaulted to 'pt_BR' for every account created outside registration.
        // NULL = no saved preference (the request / tenant default decides). Existing values are kept.
        Schema::table('users', function (Blueprint $table) {
            $table->string('language')->nullable()->default(null)->change();
        });

        DB::table('settings')->update([
            'default_locale'  => 'pt_BR',
            'enabled_locales' => json_encode(['pt_BR', 'en', 'es']),
            'timezone'        => 'America/Sao_Paulo',
            'country_code'    => 'BR',
            'document_field'  => 'optional',
            'document_type'   => 'cpf',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language')->default('pt_BR')->change();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['default_locale', 'enabled_locales', 'timezone', 'country_code', 'phone_field', 'document_field', 'document_type']);
        });
    }
};
