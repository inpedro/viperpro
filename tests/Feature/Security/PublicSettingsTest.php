<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\Tenancy;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class PublicSettingsTest extends SecurityTestCase
{
    public function test_public_settings_expose_only_the_allow_list_even_when_the_full_row_is_cached(): void
    {
        // What the admin settings pages put in the cache.
        Cache::put(Tenancy::key('setting'), Setting::query()->first());

        $setting = $this->getJson('/api/settings/data')->assertOk()->json('setting');

        $this->assertSame([], array_values(array_diff(array_keys($setting), Setting::PUBLIC_FIELDS)));
        foreach (['ngr_percent', 'revshare_percentage', 'perc_sub_lv1', 'cpa_value', 'revshare_reverse'] as $internal) {
            $this->assertArrayNotHasKey($internal, $setting);
        }
        $this->assertArrayHasKey('currency_code', $setting);
    }
}
