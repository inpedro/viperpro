<?php

namespace App\Core\Payments\Data;

/**
 * Outcome of an inbound payment webhook.
 *
 *  credited:     a deposit was settled by this call
 *  acknowledged: the request was authentic and understood, so the gateway should not retry
 *                (e.g. an already-settled deposit, or an event type we do not act on)
 */
final class WebhookResult
{
    private function __construct(public readonly bool $credited, public readonly bool $acknowledged, public readonly ?string $reason = null)
    {
    }

    public static function credited(): self
    {
        return new self(true, true);
    }

    public static function acknowledged(string $reason): self
    {
        return new self(false, true, $reason);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, false, $reason);
    }

    public function httpStatus(): int
    {
        return $this->acknowledged ? 200 : 400;
    }
}
