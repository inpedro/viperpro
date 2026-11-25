<?php

namespace App\Core\Ledger;

use LogicException;

/**
 * Ledger rows are append-only. Corrections are made with reversal journals, never by
 * editing or deleting history. (Enforced at the model layer; Phase 9 adds DB-level
 * privileges so the application user cannot UPDATE/DELETE these tables at all.)
 */
trait ImmutableLedgerRecord
{
    protected static function bootImmutableLedgerRecord(): void
    {
        static::updating(fn () => throw new LogicException('Ledger records are immutable; post a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Ledger records cannot be deleted; post a reversal instead.'));
    }
}
