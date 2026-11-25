<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\Tenancy;
use App\Models\Setting;
use App\Models\SettingMail;
use Illuminate\Support\Facades\DB;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Feature\Security\SecurityTestCase;

class TenantRuntimeConfigTest extends SecurityTestCase
{
    public function test_each_casino_gets_its_own_name_and_smtp_account_without_touching_env(): void
    {
        $envBefore = file_exists(base_path('.env')) ? hash_file('sha256', base_path('.env')) : null;

        $b = Tenant::create(['slug' => 'brand-b', 'name' => 'Brand B', 'status' => Tenant::ACTIVE]);
        TenantDomain::create(['tenant_id' => 1, 'domain' => 'a.test', 'is_primary' => true]);
        TenantDomain::create(['tenant_id' => $b->id, 'domain' => 'b.test', 'is_primary' => true]);

        Setting::query()->update(['software_name' => 'Casino A']);
        SettingMail::query()->delete();
        SettingMail::create(['software_smtp_type' => 'smtp', 'software_smtp_mail_host' => 'smtp.a.test', 'software_smtp_mail_port' => '587',
            'software_smtp_mail_password' => 'secret-a', 'software_smtp_mail_from_address' => 'no-reply@a.test']);
        Tenancy::runAs($b, function () {
            Setting::query()->update(['software_name' => 'Casino B']);
        });

        $probe = function (string $host) {
            config(['mail.mailers.smtp.host' => 'default.host']);
            $out = null;
            (new ResolveTenant())->handle(Request::create("http://{$host}/"), function () use (&$out) {
                $out = [config('app.name'), config('mail.mailers.smtp.host'), config('mail.from.address')];

                return new Response();
            });

            return $out;
        };

        $this->assertSame(['Casino A', 'smtp.a.test', 'no-reply@a.test'], $probe('a.test'));
        $this->assertSame('Casino B', $probe('b.test')[0]);
        $this->assertSame('default.host', $probe('b.test')[1], 'casino B never gets casino A\'s SMTP account');

        // Stored encrypted, readable through the model.
        $raw = DB::table('setting_mails')->where('software_smtp_mail_host', 'smtp.a.test')->value('software_smtp_mail_password');
        $this->assertNotSame('secret-a', $raw);
        Tenancy::set(Tenant::find(1));
        $this->assertSame('secret-a', SettingMail::first()->software_smtp_mail_password);

        $this->assertSame($envBefore, file_exists(base_path('.env')) ? hash_file('sha256', base_path('.env')) : null);
    }
}
