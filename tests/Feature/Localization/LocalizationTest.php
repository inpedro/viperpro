<?php

namespace Tests\Feature\Localization;

use App\Core\Localization\Formatter;
use App\Core\Localization\RegistrationPolicy;
use App\Core\Money\Money;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\Tenancy;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\SecurityTestCase;

class LocalizationTest extends SecurityTestCase
{
    private function enable(array $locales, string $default): void
    {
        Setting::query()->update(['enabled_locales' => json_encode($locales), 'default_locale' => $default]);
    }

    public function test_platform_defaults_are_english_and_utc(): void
    {
        $this->assertSame('en', config('app.locale'));
        $this->assertSame('UTC', config('locales.default') === 'en' ? config('app.timezone') : null);
        $this->getJson('/api/settings/locales')->assertOk()->assertJsonPath('current', 'en');
    }

    public function test_locale_resolution_honours_enabled_locales_accept_language_and_user_choice(): void
    {
        $this->enable(['en', 'sq'], 'en');

        $this->getJson('/api/settings/locales', ['Accept-Language' => 'sq-AL,sq;q=0.9'])->assertJsonPath('current', 'sq');
        $this->getJson('/api/settings/locales', ['Accept-Language' => 'pt-BR'])->assertJsonPath('current', 'en'); // not enabled
        $this->getJson('/api/settings/locales', ['Accept-Language' => 'de'])->assertJsonPath('current', 'en')
            ->assertJsonPath('enabled.1.native', 'Shqip');

        $user = $this->makeUser(['language' => 'sq']);
        $this->actingAs($user, 'api')->postJson('/api/profile/getLanguage', [], ['Accept-Language' => 'en'])->assertJsonPath('language', 'sq');

        $this->actingAs($user, 'api')->putJson('/api/profile/updateLanguage', ['language' => 'xx_EVIL'])->assertStatus(422);
        $this->actingAs($user, 'api')->putJson('/api/profile/updateLanguage', ['language' => 'pt_BR'])->assertStatus(422);
        $this->actingAs($user, 'api')->putJson('/api/profile/updateLanguage', ['language' => 'en'])->assertOk();
        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_api_messages_are_translated(): void
    {
        $this->enable(['en', 'sq', 'pt_BR'], 'en');
        $user = $this->makeUser(['language' => null]); // no saved preference: Accept-Language decides
        $body = ['type' => 'pix', 'amount' => '20', 'pix_type' => 'email', 'pix_key' => 'a@b.test', 'accept_terms' => true];

        $this->actingAs($user, 'api')->postJson('/api/wallet/withdraw/request', $body, ['Accept-Language' => 'sq'])
            ->assertStatus(400)->assertJsonPath('error', 'Bilanc i pamjaftueshëm');
        $this->actingAs($user, 'api')->postJson('/api/wallet/withdraw/request', $body, ['Accept-Language' => 'pt-BR'])
            ->assertJsonPath('error', 'Você não tem saldo suficiente');
    }

    public function test_rtl_locales_set_document_direction(): void
    {
        file_put_contents(lang_path('xr.json'), '{}');
        try {
            config(['locales.installed.xr' => ['name' => 'Test RTL', 'native' => 'RTL', 'intl' => 'ar', 'dir' => 'rtl']]);
            $this->enable(['en', 'xr'], 'xr');
            \Illuminate\Support\Facades\DB::table('custom_layouts')->insert(['custom_header' => '', 'custom_body' => '', 'created_at' => now(), 'updated_at' => now()]);
            $this->withoutVite();

            // (test requests send Accept-Language: en by default)
            $this->withHeader('Accept-Language', 'xr')->get('/')->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="xr"', false);
            $this->getJson('/api/settings/locales')->assertJsonPath('enabled.1.dir', 'rtl');
        } finally {
            @unlink(lang_path('xr.json'));
        }
    }

    public function test_money_and_dates_are_formatted_per_locale_in_tenant_timezone(): void
    {
        $m = Money::of('1234.50', 'EUR');
        $this->assertSame('€1,234.50', Formatter::money($m, 'en'));
        $this->assertStringContainsString('234,50', Formatter::money($m, 'sq'));
        $this->assertStringContainsString('1.234,50', Formatter::money(Money::of('1234.50', 'BRL'), 'pt_BR'));

        Setting::query()->update(['timezone' => 'Europe/Tirane']);
        $at = new \DateTimeImmutable('2026-07-01 22:30:00', new \DateTimeZone('UTC'));
        $this->assertStringContainsString('12:30', Formatter::dateTime($at, 'en')); // 00:30 next day in Tirana (UTC+2)
        $this->assertStringContainsString('Jul 2', Formatter::dateTime($at, 'en'));
    }

    public function test_registration_uses_international_phone_and_configurable_document_field(): void
    {
        $this->assertSame('+355691234567', RegistrationPolicy::normalizePhone('+355 69 123 4567'));
        $this->assertSame('+355691234567', RegistrationPolicy::normalizePhone('00355 (69) 123-4567'));
        $this->assertSame('11999998888', RegistrationPolicy::normalizePhone('(11) 99999-8888')); // legacy local format still accepted
        $this->assertNull(RegistrationPolicy::normalizePhone('call me'));

        $base = ['name' => 'P', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1', 'term_a' => 1, 'agreement' => 1];

        $this->postJson('/api/auth/register', $base + ['email' => 'a@example.test', 'phone' => 'nope'])->assertStatus(400)->assertJsonValidationErrors('phone', null);
        $this->postJson('/api/auth/register', $base + ['email' => 'a@example.test', 'phone' => '+355 69 123 4567'])->assertOk();
        $this->assertSame('+355691234567', User::where('email', 'a@example.test')->value('phone'));

        Setting::query()->update(['phone_field' => 'hidden', 'document_field' => 'required', 'document_type' => 'cpf']);
        $this->postJson('/api/auth/register', $base + ['email' => 'b@example.test', 'cpf' => '111.111.111-12'])->assertStatus(400);
        $this->postJson('/api/auth/register', $base + ['email' => 'b@example.test', 'cpf' => '529.982.247-25'])->assertOk();
        $this->assertSame('52998224725', User::where('email', 'b@example.test')->value('cpf'));
    }

    public function test_the_same_email_can_register_at_two_brands(): void
    {
        $b = Tenant::create(['slug' => 'b', 'name' => 'B', 'status' => Tenant::ACTIVE]);
        TenantDomain::create(['tenant_id' => $b->id, 'domain' => 'b.test']);
        $body = ['name' => 'P', 'email' => 'twice@example.test', 'phone' => '+15550100', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1', 'term_a' => 1, 'agreement' => 1];

        $this->postJson('http://localhost/api/auth/register', $body)->assertOk();
        $this->postJson('http://b.test/api/auth/register', $body)->assertOk();
        $this->assertSame(2, Tenancy::acrossTenants(fn () => User::where('email', 'twice@example.test')->count()));
    }

    public function test_new_tenants_are_provisioned_international(): void
    {
        $t = Tenant::create(['slug' => 'intl', 'name' => 'Intl', 'status' => Tenant::ACTIVE]);
        $s = Tenancy::runAs($t, fn () => Setting::query()->first());

        $this->assertSame('en', $s->default_locale);
        $this->assertSame('EUR', $s->currency_code);
        $this->assertSame('hidden', $s->document_field);
        $this->assertSame('UTC', $s->timezone);
    }

    public function test_translation_files_are_complete(): void
    {
        $this->assertSame(0, Artisan::call('lang:check', ['--strict' => true]), Artisan::output());
    }
}
