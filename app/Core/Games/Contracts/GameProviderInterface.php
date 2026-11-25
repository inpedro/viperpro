<?php

namespace App\Core\Games\Contracts;

use App\Core\Integrations\HealthStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Contract for casino game providers / aggregators. Methods outside getCapabilities()
 * throw App\Core\Integrations\UnsupportedCapability.
 *
 * Wallet effects of processBet/processWin/processRefund/processRollback MUST go through
 * App\Core\Wallet\WalletService with the provider transaction id as idempotency key.
 */
interface GameProviderInterface
{
    public const CAP_LOBBY = 'lobby';
    public const CAP_LAUNCH = 'launch';
    public const CAP_SEAMLESS_WALLET = 'seamless_wallet';
    public const CAP_TRANSFER_WALLET = 'transfer_wallet';
    public const CAP_ROLLBACK = 'rollback';
    public const CAP_AUTHENTICATED_CALLBACKS = 'authenticated_callbacks';

    public function getIdentifier(): string;

    public function getName(): string;

    /** @return string[] */
    public function getSupportedCurrencies(): array;

    /** @return string[] */
    public function getSupportedLanguages(): array;

    /** @return string[] self::CAP_* */
    public function getCapabilities(): array;

    public function getLobbyGames(): array;

    public function getGameDetails(string $gameCode): ?array;

    /** Returns the launch URL (or provider payload) for the player. */
    public function launchGame(Game $game, User $player, array $options = []): mixed;

    /** Null when the callback is authenticated, otherwise the rejection reason. */
    public function validateCallback(Request $request): ?string;

    public function processBet(Request $request): mixed;

    public function processWin(Request $request): mixed;

    public function processRefund(Request $request): mixed;

    public function processRollback(Request $request): mixed;

    public function getBalance(User $player): ?string;

    public function getHealthStatus(): HealthStatus;
}
