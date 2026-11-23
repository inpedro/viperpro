<?php

namespace App\Core\Money;

use InvalidArgumentException;

/**
 * Immutable money value: integer minor units + ISO-ish currency code.
 *
 * Precision is fixed at 2 decimals for now because every wallet column is decimal(20,2).
 * Currencies needing other precisions (JPY = 0, USDT = 6) require widening those columns
 * first; see docs/LEDGER.md. Mixing currencies throws, never converts.
 */
final class Money
{
    public const PRECISION = 2;

    private function __construct(public readonly int $minor, public readonly string $currency)
    {
        if (! preg_match('/^[A-Z0-9]{3,10}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency code [{$currency}]");
        }
    }

    public static function ofMinor(int $minor, string $currency): self
    {
        return new self($minor, strtoupper($currency));
    }

    /**
     * From a decimal string/int ("12.50", 12). Floats are accepted only for legacy call
     * sites and are rounded to 2 decimals; new code must pass strings.
     */
    public static function of(int|float|string $amount, string $currency): self
    {
        $minor = Amount::toMinor($amount);
        if ($minor === null) {
            throw new InvalidArgumentException('Invalid monetary amount');
        }

        return self::ofMinor($minor, $currency);
    }

    public static function zero(string $currency): self
    {
        return self::ofMinor(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);
        return self::ofMinor($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);
        return self::ofMinor($this->minor - $other->minor, $this->currency);
    }

    public function negate(): self
    {
        return self::ofMinor(-$this->minor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minor >= $other->minor;
    }

    public function toDecimal(): string
    {
        return Amount::fromMinor($this->minor);
    }

    public function __toString(): string
    {
        return $this->toDecimal().' '.$this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}");
        }
    }
}
