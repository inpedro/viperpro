<?php

namespace App\Core\Wallet;

/**
 * Labels balance changes made by legacy (not yet migrated) code so the observed journals
 * carry a business type and a counter account instead of "unattributed".
 *
 *   LedgerContext::run('game.win', 'game', fn () => Helper::payWithRollover(...), ['reference_id' => $txn]);
 */
final class LedgerContext
{
    /** @var array<int, array{type: string, counter: string, context: array}> */
    private static array $stack = [];

    public static function run(string $type, string $counter, callable $callback, array $context = []): mixed
    {
        self::$stack[] = ['type' => $type, 'counter' => $counter, 'context' => $context];

        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function current(): array
    {
        return end(self::$stack) ?: ['type' => 'unattributed', 'counter' => 'unattributed', 'context' => []];
    }
}
