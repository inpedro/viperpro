<?php

namespace App\Http\Controllers\Gateway\Concerns;

use App\Core\Payments\WithdrawalService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared admin-side withdrawal approval/cancellation for gateway controllers.
 *
 * These are invoked from authenticated Filament actions (Livewire POST + CSRF),
 * never from public GET routes. The gateway controller only supplies sendPayout().
 */
trait HandlesWithdrawals
{
    /**
     * Send the payout through this gateway. Return true only when the gateway accepted it.
     */
    abstract protected function sendPayout(Model $withdrawal, string $type): bool;

    public function approveWithdrawal(int|string $id, string $type): bool
    {
        $ok = WithdrawalService::approve($type, $id, fn (Model $w) => $this->sendPayout($w, $type));

        $ok
            ? Notification::make()->title('Withdrawal sent')->body('The payout was accepted by the gateway.')->success()->send()
            : Notification::make()->title('Withdrawal not sent')->body('The withdrawal is not pending or the gateway rejected the payout.')->danger()->send();

        return $ok;
    }

    public function cancelWithdrawal(int|string $id, string $type): bool
    {
        $ok = WithdrawalService::cancel($type, $id);

        $ok
            ? Notification::make()->title('Withdrawal cancelled')->body('The amount was returned to the player balance.')->success()->send()
            : Notification::make()->title('Withdrawal not cancelled')->body('The withdrawal is no longer pending.')->danger()->send();

        return $ok;
    }
}
