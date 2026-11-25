<?php

namespace Tests\Feature\Release;

use App\Core\Tenancy\Tenancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Every parameterless page of the staff panels must render for an MFA-verified user.
 * Guards framework/Filament upgrades, which the functional tests only partly exercise.
 */
class AdminPanelSmokeTest extends SecurityTestCase
{
    public function test_every_casino_admin_page_renders(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = $this->makeUser();
        $admin->assignRole('admin');

        $this->assertPanelRenders('admin', $admin, 'http://localhost');
    }

    public function test_every_platform_page_renders(): void
    {
        config(['tenancy.platform_domains' => ['platform.test']]);
        Tenancy::setPlatform();
        Role::findOrCreate('super-admin', 'web');
        $root = new User();
        $root->forceFill(['name' => 'Root', 'email' => 'root@platform.test', 'password' => 'very-long-password'])->save();
        $root->assignRole('super-admin');

        $this->assertPanelRenders('platform', $root, 'http://platform.test');
    }

    public function test_the_admin_panel_is_localized(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = $this->makeUser();
        $admin->assignRole('admin');
        $this->withCookie(config('session.cookie'), str_repeat('d', 40));
        $this->actingAs($admin)->get('/admin/mfa');
        DB::table('admin_sessions')->update(['mfa_verified' => true]);
        DB::table('settings')->update(['enabled_locales' => json_encode(['en', 'sq', 'es', 'pt_BR'])]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->actingAs($admin)->withHeader('Accept-Language', 'sq')->get('/admin/users')
            ->assertOk()->assertSee('Përdoruesit')->assertDontSee('Usuários');
        $this->actingAs($admin)->withHeader('Accept-Language', 'en')->get('/admin/users')
            ->assertOk()->assertSee('Users')->assertDontSee('Usuários');
        $this->actingAs($admin)->withHeader('Accept-Language', 'pt-BR')->get('/admin/users')
            ->assertOk()->assertSee('Usuários');
    }

    private function assertPanelRenders(string $panel, User $user, string $base): void
    {
        $this->withCookie(config('session.cookie'), str_repeat('b', 40));
        $this->actingAs($user)->get("{$base}/{$panel}/mfa");
        DB::table('admin_sessions')->update(['mfa_verified' => true]);

        $uris = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), "filament.{$panel}.")
                && in_array('GET', $r->methods(), true)
                && ! str_contains($r->uri(), '{')
                && ! preg_match('#(logout|mfa|login)$#', $r->uri()))
            ->map(fn ($r) => '/'.$r->uri())->unique()->sort()->values();

        $this->assertGreaterThan(3, $uris->count());

        $failures = [];
        $rendered = 0;
        foreach ($uris as $uri) {
            $res = $this->actingAs($user)->get($base.$uri);
            $rendered += (int) ($res->getStatusCode() === 200);
            if (! in_array($res->getStatusCode(), [200, 302, 403], true)) {
                $e = $res->exception;
                $failures[] = "{$uri} -> {$res->getStatusCode()}".($e ? ' '.get_class($e).': '.substr($e->getMessage(), 0, 200).' @ '.$e->getFile().':'.$e->getLine() : '');
            }
        }

        $this->assertSame([], $failures);
        $this->assertGreaterThanOrEqual((int) ceil($uris->count() * 0.8), $rendered, 'most pages must actually render, not redirect');
    }
}
