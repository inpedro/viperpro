<?php

namespace App\Core\Games\Providers;

use App\Core\Games\Contracts\GameProviderInterface;
use App\Core\Integrations\HealthStatus;
use App\Core\Integrations\UnsupportedCapability;
use App\Core\Security\ProviderCallbackAuth;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Descriptor for a provider whose protocol still lives in a legacy trait
 * (app/Traits/Providers/*). It is honest about that: callback authentication and health are
 * implemented here; protocol operations throw UnsupportedCapability and name the trait that
 * currently handles them. Each provider is rebuilt as a full adapter in Phase 7, against its
 * official documentation and sandbox.
 */
class LegacyGameProvider implements GameProviderInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $legacyTrait,
        private readonly bool $callbacksMounted,
        private readonly array $currencies = [],
    ) {
    }

    public function getIdentifier(): string { return $this->id; }

    public function getName(): string { return $this->name; }

    public function getSupportedCurrencies(): array { return $this->currencies; }

    public function getSupportedLanguages(): array { return []; }

    public function getCapabilities(): array
    {
        return ProviderCallbackAuth::isConfigured($this->id) ? [self::CAP_AUTHENTICATED_CALLBACKS] : [];
    }

    public function validateCallback(Request $request): ?string
    {
        return ProviderCallbackAuth::check($this->id, $request);
    }

    public function getHealthStatus(): HealthStatus
    {
        if (! $this->callbacksMounted) {
            return new HealthStatus(HealthStatus::DISABLED, ["Callback routes are not mounted (legacy {$this->legacyTrait})."]);
        }

        if (! ProviderCallbackAuth::isConfigured($this->id)) {
            return new HealthStatus(HealthStatus::DISABLED, ['Callbacks rejected (503): configure '.strtoupper($this->id).'_CALLBACK_IPS and/or _CALLBACK_SECRET.']);
        }

        return new HealthStatus(HealthStatus::UNVERIFIED, ["Callbacks authenticated; protocol handled by legacy {$this->legacyTrait} (no ledger idempotency per provider txn yet)."]);
    }

    public function getLobbyGames(): array { throw $this->legacy('lobby sync'); }

    public function getGameDetails(string $gameCode): ?array { throw $this->legacy('game details'); }

    public function launchGame(Game $game, User $player, array $options = []): mixed { throw $this->legacy('launch'); }

    public function processBet(Request $request): mixed { throw $this->legacy('bets'); }

    public function processWin(Request $request): mixed { throw $this->legacy('wins'); }

    public function processRefund(Request $request): mixed { throw $this->legacy('refunds'); }

    public function processRollback(Request $request): mixed { throw $this->legacy('rollbacks'); }

    public function getBalance(User $player): ?string { throw $this->legacy('balance'); }

    private function legacy(string $what): UnsupportedCapability
    {
        return UnsupportedCapability::for($this->name, $what, "Still handled by {$this->legacyTrait} via the existing routes.");
    }
}
