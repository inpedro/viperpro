<?php

namespace App\Filament\Admin\Support;

use App\Core\Payments\PaymentGateways;
use App\Core\Payments\WithdrawalService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-side withdrawal actions (invoked from confirmed Filament actions: Livewire POST + CSRF).
 */
class WithdrawalActions
{
    public static function approve(int|string $id, string $type): void
    {
        $gateway = PaymentGateways::default();

        if (! $gateway || ! $gateway->getHealthStatus()->isUsable()) {
            Notification::make()->title(__('No usable payout gateway'))
                ->body(__('Configure the default gateway (Settings) and its credentials first.'))->danger()->send();
            return;
        }

        $ok = WithdrawalService::approve($type, $id, fn (Model $w) => $gateway->createWithdrawal($w, $type));

        $ok
            ? Notification::make()->title(__('Withdrawal sent'))->body('The payout was accepted by '.$gateway->getName().'.')->success()->send()
            : Notification::make()->title(__('Withdrawal not sent'))->body(__('The withdrawal is not pending or the gateway rejected the payout.'))->danger()->send();
    }

    public static function cancel(int|string $id, string $type): void
    {
        WithdrawalService::cancel($type, $id)
            ? Notification::make()->title(__('Withdrawal cancelled'))->body(__('The amount was returned to the balance.'))->success()->send()
            : Notification::make()->title(__('Withdrawal not cancelled'))->body(__('The withdrawal is no longer pending.'))->danger()->send();
    }
}
