<?php

namespace App\Core\Games;

use App\Core\Games\Contracts\GameProviderInterface;
use App\Core\Games\Providers\LegacyGameProvider;
use InvalidArgumentException;

class GameProviders
{
    public static function get(string $id): GameProviderInterface
    {
        $row = config("game_providers.providers.{$id}");
        if (! is_array($row)) {
            throw new InvalidArgumentException("Game provider [{$id}] is not installed");
        }

        [$name, $trait, $mounted] = $row;

        return new LegacyGameProvider($id, $name, $trait, (bool) $mounted);
    }

    /** @return GameProviderInterface[] */
    public static function all(): array
    {
        return array_map(fn ($id) => self::get($id), array_keys((array) config('game_providers.providers', [])));
    }
}
