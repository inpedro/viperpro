<?php

namespace App\Core\Payments\Contracts;

/**
 * Operator-initiated payout from the merchant account (not from a player wallet, so it has
 * no ledger effect). Every call is written to audit_logs by the caller.
 */
interface SupportsManualPayout
{
    /**
     * @param array{user_id?: int|null, pix_key: string, pix_type: string, amount: string, observation?: string|null} $data
     */
    public function manualPayout(array $data): bool;
}
