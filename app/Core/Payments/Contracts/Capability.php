<?php

namespace App\Core\Payments\Contracts;

final class Capability
{
    public const DEPOSIT = 'deposit';
    public const DEPOSIT_VERIFICATION = 'deposit_verification';   // server-to-server status lookup
    public const SIGNED_WEBHOOKS = 'signed_webhooks';
    public const AMOUNT_VERIFICATION = 'amount_verification';     // status API reports amount + currency
    public const WITHDRAWAL = 'withdrawal';
    public const WITHDRAWAL_VERIFICATION = 'withdrawal_verification';
    public const REFUND = 'refund';
}
