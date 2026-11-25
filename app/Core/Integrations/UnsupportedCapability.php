<?php

namespace App\Core\Integrations;

/**
 * Thrown when an integration is asked for something it does not implement. Adapters never
 * fake a capability: they either implement it against the provider's real API or throw this.
 */
class UnsupportedCapability extends \RuntimeException
{
    public static function for(string $integration, string $capability, string $why = ''): self
    {
        return new self(trim("{$integration} does not support {$capability}. {$why}"));
    }
}
