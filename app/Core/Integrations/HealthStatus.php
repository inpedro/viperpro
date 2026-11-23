<?php

namespace App\Core\Integrations;

final class HealthStatus
{
    public const OK = 'ok';
    public const DISABLED = 'disabled';          // not configured / fail closed
    public const DEGRADED = 'degraded';
    public const UNVERIFIED = 'unverified';      // configured, but the integration has not been verified against provider docs

    public function __construct(public readonly string $status, public readonly array $messages = [])
    {
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::OK, self::DEGRADED, self::UNVERIFIED], true);
    }
}
