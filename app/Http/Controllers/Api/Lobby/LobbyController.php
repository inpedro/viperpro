<?php

namespace App\Http\Controllers\Api\Lobby;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Game;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Provider-independent lobby API (Phase 6). Game metadata is normalized here so the
 * frontend never depends on a provider's field names or on how covers are stored.
 */
class LobbyController extends Controller
{
    public const SECTIONS = ['all', 'featured', 'popular', 'new'];
    public const MAX_PER_PAGE = 48;

    /**
     * GET /api/lobby/games?section=popular&category=slots&provider=3&q=fortune&per_page=24&page=1
     */
    public function games(Request $request)
    {
        $data = $request->validate([
            'section'  => ['nullable', 'in:'.implode(',', self::SECTIONS)],
            'category' => ['nullable', 'string', 'max:80'],
            'provider' => ['nullable', 'integer', 'min:1'],
            'q'        => ['nullable', 'string', 'max:60'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $query = Game::query()
            ->with(['provider:id,name', 'categories:id,name,slug'])
            ->where('status', 1)
            ->whereHas('provider', fn ($p) => $p->where('status', 1));

        if (! empty($data['category']) && $data['category'] !== 'all') {
            $query->whereHas('categories', fn ($c) => $c->where('slug', $data['category']));
        }
        if (! empty($data['provider'])) {
            $query->where('provider_id', $data['provider']);
        }
        if (isset($data['q']) && mb_strlen(trim($data['q'])) >= 2) {
            $term = '%'.addcslashes(trim($data['q']), '%_\\').'%';
            $query->where(fn ($w) => $w->where('game_name', 'like', $term)->orWhere('game_code', 'like', $term));
        }

        match ($data['section'] ?? 'all') {
            'featured' => $query->where('is_featured', 1)->orderByDesc('views'),
            'popular'  => $query->orderByDesc('views'),
            'new'      => $query->orderByDesc('created_at'),
            default    => $query->orderBy('game_name'),
        };

        $page = $query->paginate((int) ($data['per_page'] ?? 24))->appends($request->query());

        return response()->json([
            'data' => collect($page->items())->map(fn (Game $g) => self::present($g))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function providers()
    {
        // Counted directly: Provider::games() is filtered to show_home games, which would undercount.
        $counts = Game::query()->where("status", 1)
            ->selectRaw("provider_id, COUNT(*) AS c")->groupBy("provider_id")->pluck("c", "provider_id");

        $providers = Provider::query()->where("status", 1)->whereIn("id", $counts->keys())->orderBy("name")->get(["id", "name"]);

        return response()->json(["data" => $providers->map(fn ($p) => [
            "id" => $p->id, "name" => $p->name, "games" => (int) $counts[$p->id],
        ])->values()]);
    }

    public function categories()
    {
        return response()->json(['data' => Category::query()->orderBy('name')->get(['id', 'name', 'slug'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values()]);
    }

    /** Normalized game card. */
    public static function present(Game $g): array
    {
        $g->setAppends([]); // the model's default appends run several queries per game

        $cover = (string) ($g->cover ?? '');
        $thumbnail = $cover === '' ? null : (Str::startsWith($cover, ['http://', 'https://']) ? $cover : asset('storage/'.ltrim($cover, '/')));

        return [
            'id'          => $g->id,
            'name'        => $g->game_name,
            'code'        => $g->game_code,
            'thumbnail'   => $thumbnail,
            'provider'    => $g->provider ? ['id' => $g->provider->id, 'name' => $g->provider->name] : null,
            'categories'  => $g->categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values(),
            'is_featured' => (bool) $g->is_featured,
            // Game::created_at has a display accessor; read the raw column.
            'is_new'      => ($created = $g->getRawOriginal('created_at')) !== null && \Illuminate\Support\Carbon::parse($created)->greaterThan(now()->subDays(30)),
            'has_demo'    => (bool) $g->only_demo,
            'rtp'         => is_numeric($g->rtp) ? (float) $g->rtp : null,
        ];
    }
}
