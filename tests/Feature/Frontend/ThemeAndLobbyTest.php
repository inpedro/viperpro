<?php

namespace Tests\Feature\Frontend;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use App\Core\Theming\Theme;
use App\Filament\Admin\Pages\ThemePage;
use App\Models\Category;
use App\Models\CustomLayout;
use App\Models\Game;
use App\Models\Provider;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

class ThemeAndLobbyTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('custom_layouts')->insert(['custom_header' => '', 'custom_body' => '', 'created_at' => now(), 'updated_at' => now()]);
        $this->withoutVite();
    }

    public function test_four_themes_are_defined_with_complete_tokens(): void
    {
        $required = ['bg', 'surface', 'surface-2', 'border', 'text', 'muted', 'primary', 'primary-contrast', 'accent', 'success', 'warning', 'danger'];
        $this->assertSame(['obsidian', 'royal', 'minimal', 'neon'], array_keys(Theme::presets()));

        foreach (Theme::presets() as $key => $p) {
            $this->assertSame([], array_diff($required, array_keys($p['colors'])), $key);
            foreach ($p['colors'] as $hex) {
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $hex);
            }
        }
    }

    public function test_legacy_mode_keeps_existing_colours_and_theme_mode_emits_tokens(): void
    {
        // Legacy: tokens derived from the tenant's colours, but --ci-* variables are not overridden.
        \Illuminate\Support\Facades\DB::table('custom_layouts')->update(['primary_color' => '#1da639']);
        $this->get('/')->assertOk()->assertDontSee('data-theme=', false)
            ->assertSee('--vp-primary:#1DA639', false)->assertDontSee('--ci-primary-color:#', false);

        CustomLayout::query()->update(['theme' => 'minimal']);
        $this->get('/')->assertOk()
            ->assertSee('data-theme="minimal"', false)
            ->assertSee('class="light"', false)
            ->assertSee('--vp-primary:#111827 !important', false)
            ->assertSee('--ci-primary-color:#111827 !important', false);
    }

    public function test_overrides_cannot_inject_css(): void
    {
        $clean = Theme::sanitizeOverrides([
            'primary' => 'red;}</style><script>alert(1)</script>',
            'accent'  => '#00ff00',
            'radius'  => '999',
            'font'    => "'Comic Sans'; background:url(x)",
        ]);
        $this->assertSame(['accent' => '#00FF00'], $clean);

        CustomLayout::query()->update(['theme' => 'obsidian', 'theme_overrides' => json_encode(['primary' => 'x;}</style><script>bad()</script>', 'accent' => '#123456'])]);
        // Not in the CSS, and not able to break out of the inline <script> config either.
        $this->get('/')->assertOk()->assertDontSee('<script>bad()', false)->assertDontSee('x;}</style>', false)->assertSee('--vp-accent:#123456 !important', false);
    }

    public function test_primary_override_gets_an_accessible_contrast_colour(): void
    {
        $this->assertSame('#0B0D12', Theme::contrastFor('#FFE600'));
        $this->assertSame('#FFFFFF', Theme::contrastFor('#1E3A8A'));
        $this->assertSame('#0B0D12', Theme::tokens('obsidian', ['primary' => '#FFE600'])['colors']['primary-contrast']);
    }

    public function test_admin_saves_theme_per_tenant(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = $this->makeUser();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(ThemePage::class)
            ->set('data.theme', 'royal')
            ->set('data.primary', '#B8860B')
            ->set('data.radius', 6)
            ->call('save')->assertHasNoErrors();

        $this->assertSame('royal', Theme::activeKey());
        $this->assertSame('#B8860B', Theme::tokens()['colors']['primary']);
        $this->assertSame(6, Theme::tokens()['radius']);

        $b = Tenant::create(['slug' => 'b', 'name' => 'B', 'status' => Tenant::ACTIVE]);
        $this->assertSame('obsidian', Tenancy::runAs($b, fn () => Theme::activeKey())); // new tenants default to obsidian
    }

    private function seedCatalogue(): array
    {
        $p1 = Provider::create(['code' => 'p1', 'name' => 'Alpha Games', 'status' => 1]);
        $p2 = Provider::create(['code' => 'p2', 'name' => 'Beta Studio', 'status' => 1]);
        Provider::create(['code' => 'p3', 'name' => 'Empty', 'status' => 1]);
        $slots = Category::create(['name' => 'Slots', 'slug' => 'slots', 'description' => 'Slots']);

        $mk = fn ($p, $name, $views, $extra = []) => Game::create(array_merge([
            'provider_id' => $p->id, 'game_name' => $name, 'game_code' => strtolower(str_replace(' ', '-', $name)), 'game_id' => uniqid(),
            'cover' => 'covers/'.strtolower($name).'.png', 'status' => 1, 'views' => $views, 'distribution' => 'source', 'rtp' => 96,
        ], $extra));

        $a = $mk($p1, 'Aztec Gold', 10);
        $b = $mk($p1, 'Book Quest', 99, ['is_featured' => 1, 'cover' => 'https://cdn.example.test/book.png']);
        $mk($p2, 'Crystal Run', 50);
        $mk($p2, 'Disabled Game', 500, ['status' => 0]);
        $a->categories()->attach($slots->id);
        $b->categories()->attach($slots->id);

        return [$p1, $p2, $slots];
    }

    public function test_lobby_games_are_normalized_filtered_and_bounded(): void
    {
        [$p1, $p2] = $this->seedCatalogue();

        $res = $this->getJson('/api/lobby/games?section=popular')->assertOk();
        $this->assertSame(['Book Quest', 'Crystal Run', 'Aztec Gold'], array_column($res->json('data'), 'name'));
        $this->assertSame('https://cdn.example.test/book.png', $res->json('data.0.thumbnail'));
        $this->assertStringEndsWith('/storage/covers/crystal run.png', $res->json('data.1.thumbnail'));
        $this->assertSame(['id' => $p2->id, 'name' => 'Beta Studio'], $res->json('data.1.provider'));
        $this->assertArrayNotHasKey('hasFavorite', $res->json('data.0'));

        $this->assertSame(['Book Quest'], array_column($this->getJson('/api/lobby/games?section=featured')->json('data'), 'name'));
        $this->assertSame(['Aztec Gold', 'Book Quest'], array_column($this->getJson('/api/lobby/games?category=slots')->json('data'), 'name'));
        $this->assertSame(['Crystal Run'], array_column($this->getJson('/api/lobby/games?provider='.$p2->id)->json('data'), 'name'));
        $this->assertSame(['Crystal Run'], array_column($this->getJson('/api/lobby/games?q=crys')->json('data'), 'name'));
        $this->assertSame(0, $this->getJson('/api/lobby/games?q=%25%25')->json('meta.total')); // LIKE wildcards are escaped

        $this->getJson('/api/lobby/games?per_page=500')->assertStatus(422);
        $this->getJson('/api/lobby/games?section=drop-table')->assertStatus(422);
    }

    public function test_lobby_providers_and_categories(): void
    {
        $this->seedCatalogue();

        $this->assertSame([['name' => 'Alpha Games', 'games' => 2], ['name' => 'Beta Studio', 'games' => 1]],
            array_map(fn ($p) => ['name' => $p['name'], 'games' => $p['games']], $this->getJson('/api/lobby/providers')->json('data')));
        $this->assertSame('slots', $this->getJson('/api/lobby/categories')->json('data.0.slug'));
    }
}
