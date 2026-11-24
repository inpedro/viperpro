<?php

namespace Tests\Feature\Localization;

use App\Filament\Admin\Pages\LocalizationSettingsPage;
use App\Models\Setting;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

class LocalizationSettingsPageTest extends SecurityTestCase
{
    public function test_tenant_admin_configures_languages_and_registration_fields(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = $this->makeUser();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(LocalizationSettingsPage::class)
            ->set('data.enabled_locales', ['en', 'sq'])
            ->set('data.default_locale', 'sq')
            ->set('data.timezone', 'Europe/Tirane')
            ->set('data.phone_field', 'optional')
            ->set('data.document_field', 'hidden')
            ->call('save')
            ->assertHasNoErrors();

        $s = Setting::query()->first();
        $this->assertSame(['en', 'sq'], json_decode($s->getAttributes()['enabled_locales'], true));
        $this->assertSame('sq', $s->default_locale);
        $this->assertSame('Europe/Tirane', $s->timezone);
        $this->getJson('/api/settings/locales', ['Accept-Language' => 'fr'])->assertJsonPath('current', 'sq');

        Livewire::test(LocalizationSettingsPage::class)
            ->set('data.enabled_locales', ['en'])
            ->set('data.default_locale', 'sq')
            ->call('save')
            ->assertHasErrors('data.default_locale');
    }
}
