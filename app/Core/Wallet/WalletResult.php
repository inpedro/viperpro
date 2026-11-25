<?php

namespace App\Core\Wallet;

use App\Core\Ledger\Models\LedgerJournal;
use App\Models\Wallet;

final class WalletResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly Wallet $wallet,
        public readonly ?LedgerJournal $journal = null,
        public readonly ?string $reason = null,
        public readonly bool $duplicate = false,
        public readonly ?string $bucket = null,
    ) {
    }

    public static function success(Wallet $wallet, LedgerJournal $journal): self
    {
        return new self(true, $wallet, $journal);
    }

    public static function failed(string $reason, Wallet $wallet): self
    {
        return new self(false, $wallet, null, $reason);
    }

    /** The idempotency key was already processed: nothing changed, treat as success for retries. */
    public static function duplicate(Wallet $wallet): self
    {
        return new self(true, $wallet, null, 'duplicate', true);
    }

    public function withBucket(string $bucket): self
    {
        return new self($this->ok, $this->wallet, $this->journal, $this->reason, $this->duplicate, $bucket);
    }
}
