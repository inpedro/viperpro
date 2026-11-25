<?php

namespace App\Core\Payments\Data;

final class VerificationResult
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const UNKNOWN = 'unknown';   // provider unreachable, not configured, or unrecognised status

    public function __construct(
        public readonly string $status,
        public readonly ?string $amount = null,     // decimal string, only if the provider reports it
        public readonly ?string $currency = null,
        public readonly ?string $providerStatus = null,
    ) {
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
