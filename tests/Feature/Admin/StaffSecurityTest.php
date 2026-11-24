<?php

namespace Tests\Feature\Admin;

use App\Core\Security\Mfa;
use App\Core\Security\Totp;
use App\Filament\Admin\Pages\ThemePage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

class StaffSecurityTest extends SecurityTestCase
{
    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $u = $this->makeUser();
        $u->assignRole('admin');

        return $u;
    }

    public function test_totp_matches_rfc6238_vectors_and_rejects_replay(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('287082', Totp::codeAt($secret, intdiv(59, 30)));
        $this->assertSame('081804', Totp::codeAt($secret, intdiv(1111111109, 30)));
        $this->assertSame('005924', Totp::codeAt($secret, intdiv(1234567890, 30)));

        $step = Totp::verify($secret, Totp::codeAt($secret, Totp::currentStep()));
        $this->assertNotNull($step);
        $this->assertNull(Totp::verify($secret, Totp::codeAt($secret, $step), $step), 'a code must not be accepted twice');
        $this->assertNull(Totp::verify($secret, '000000x'));
    }

    public function test_enrollment_verification_and_single_use_recovery_codes(): void
    {
        $admin = $this->admin();
        $secret = Totp::generateSecret();

        $this->assertNull(Mfa::enable($admin, $secret, '123456'));
        $codes = Mfa::enable($admin, $secret, Totp::codeAt($secret, Totp::currentStep()));
        $this->assertCount(8, $codes);
        $this->assertStringNotContainsString($secret, (string) DB::table('users')->where('id', $admin->id)->value('mfa_secret'), 'secret encrypted at rest');

        $this->assertTrue(Mfa::verify($admin->fresh(), $codes[0]));
        $this->assertFalse(Mfa::verify($admin->fresh(), $codes[0]), 'recovery codes are single use');
        $this->assertFalse(Mfa::verify($admin->fresh(), '999999'));
        $this->assertTrue(Mfa::verify($admin->fresh(), Totp::codeAt($secret, Totp::currentStep() + 1)));
    }

    public function test_admin_panel_requires_mfa_and_revoked_sessions_are_logged_out(): void
    {
        $admin = $this->admin();
        // Keep one session id across requests, as a browser would.
        $this->withCookie(config('session.cookie'), str_repeat('a', 40));

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/mfa');
        $this->actingAs($admin)->get('/admin/mfa')->assertOk()->assertSee(__('Set up two-factor authentication'));

        DB::table('admin_sessions')->update(['mfa_verified' => true]);
        $this->actingAs($admin)->get('/admin/analytics')->assertOk();

        DB::table('admin_sessions')->update(['revoked_at' => now()]);
        $this->actingAs($admin)->get('/admin/analytics')->assertRedirect();
        $this->assertGuest();
    }

    public function test_staff_changes_are_audited_without_values(): void
    {
        $this->actingAs($this->admin());
        DB::table('custom_layouts')->insert(['custom_header' => '', 'custom_body' => '', 'created_at' => now(), 'updated_at' => now()]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ThemePage::class)->set('data.theme', 'neon')->call('save');

        $log = DB::table('audit_logs')->where('action', 'admin.updated')->where('subject_type', 'CustomLayout')->first();
        $this->assertNotNull($log);
        $this->assertContains('theme', json_decode($log->meta, true)['fields']);
        $this->assertStringNotContainsString('neon', $log->meta);
    }

    public function test_security_headers_are_sent(): void
    {
        $res = $this->getJson('/api/settings/locales');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("object-src 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('script-src', $res->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_password_reset_revokes_existing_tokens(): void
    {
        $user = $this->makeUser();
        $token = auth('api')->login($user);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/verify')->assertOk();

        $this->travel(2)->seconds();
        $user->forceFill(['tokens_valid_after' => now()])->save();
        auth('api')->forgetUser();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/verify')->assertStatus(401);
    }
}
