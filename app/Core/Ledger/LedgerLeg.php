<?php

namespace App\Core\Ledger;

use App\Core\Ledger\Models\LedgerAccount;
use App\Core\Money\Money;

/**
 * One side of a posting. Positive amount = increases the account, negative = decreases it.
 */
final class LedgerLeg
{
    public function __construct(
        public readonly LedgerAccount $account,
        public readonly Money $amount,
        public readonly ?int $balanceAfterMinor = null,
    ) {
        if ($account->currency !== $amount->currency) {
            throw new \InvalidArgumentException("Leg currency {$amount->currency} does not match account {$account->code}");
        }
    }
}
