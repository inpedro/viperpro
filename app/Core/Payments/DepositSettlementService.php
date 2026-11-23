<?php

namespace App\Core\Payments;

use App\Helpers\Core as Helper;
use App\Models\AffiliateHistory;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\NewDepositNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single, atomic, idempotent deposit settlement.
 *
 * Replaces the five copy-pasted finalizePayment*() implementations that used to
 * live in each gateway trait. Callers MUST only invoke settle() after the payment
 * has been confirmed server-to-server with the gateway (never on the strength of
 * an inbound callback body alone).
 *
 * Guarantees:
 *  - The pending Transaction row and the Wallet row are locked (SELECT ... FOR UPDATE)
 *    inside a DB transaction, so concurrent callbacks/polls cannot double-credit.
 *  - A transaction already settled (status != 0) is a no-op returning false.
 *  - When the gateway reports the paid amount, it must match the expected amount.
 */
class DepositSettlementService
{
    /**
     * @param string      $paymentId       Gateway transaction id stored in transactions.payment_id
     * @param string|null $confirmedAmount Amount the gateway says was paid (null if the gateway API does not report it)
     * @param string|null $confirmedCurrency Currency the gateway says was paid (null if not reported)
     */
    public static function settle(string $paymentId, ?string $confirmedAmount = null, ?string $confirmedCurrency = null): bool
    {
        $notify = null;

        $settled = DB::transaction(function () use ($paymentId, $confirmedAmount, $confirmedCurrency, &$notify) {
            $transaction = Transaction::where('payment_id', $paymentId)->lockForUpdate()->first();

            if (! $transaction || (int) $transaction->status !== 0) {
                return false; // unknown or already settled: idempotent no-op
            }

            if ($confirmedAmount !== null && ! self::amountsMatch((string) $transaction->price, $confirmedAmount)) {
                Log::warning('deposit.settle.amount_mismatch', [
                    'payment_id' => $paymentId,
                    'expected'   => (string) $transaction->price,
                    'confirmed'  => $confirmedAmount,
                ]);
                return false;
            }

            if ($confirmedCurrency !== null && ! empty($transaction->currency)
                && strcasecmp($confirmedCurrency, (string) $transaction->currency) !== 0) {
                Log::warning('deposit.settle.currency_mismatch', [
                    'payment_id' => $paymentId,
                    'expected'   => $transaction->currency,
                    'confirmed'  => $confirmedCurrency,
                ]);
                return false;
            }

            $user   = User::find($transaction->user_id);
            $wallet = Wallet::where('user_id', $transaction->user_id)->lockForUpdate()->first();
            if (! $user || ! $wallet) {
                return false;
            }

            $setting = Helper::getSetting();
            $price   = $transaction->price;

            $isFirstDeposit = Transaction::where('user_id', $transaction->user_id)->where('status', 1)->count() === 0;

            if ($isFirstDeposit && $transaction->accept_bonus) {
                $bonus = Helper::porcentagem_xn($setting->initial_bonus, $price);
                $wallet->increment('balance_bonus', $bonus);

                if (! $setting->disable_rollover) {
                    $wallet->update(['balance_bonus_rollover' => $bonus * $setting->rollover]);
                }
            }

            if (! $setting->disable_rollover) {
                $wallet->increment('balance_deposit_rollover', $price * intval($setting->rollover_deposit));
            }

            Helper::payBonusVip($wallet, $price);

            // With rollover disabled the deposit is immediately withdrawable; otherwise it is play-only balance.
            $wallet->increment($setting->disable_rollover ? 'balance_withdrawal' : 'balance', $price);

            $transaction->update(['status' => 1]);

            $deposit = Deposit::where('payment_id', $paymentId)->where('status', 0)->lockForUpdate()->first();
            if ($deposit) {
                self::payAffiliateCpa($user, $deposit, $price);
                $deposit->update(['status' => 1]);
            }

            $notify = [$user->name, $price];

            return true;
        });

        if ($settled && $notify) {
            foreach (User::where('role_id', 0)->get() as $admin) {
                $admin->notify(new NewDepositNotification($notify[0], $notify[1]));
            }
        }

        return $settled;
    }

    public static function amountsMatch(string $expected, string $confirmed): bool
    {
        return \App\Core\Money\Amount::equals($expected, $confirmed);
    }

    private static function payAffiliateCpa(User $user, Deposit $deposit, $price): void
    {
        $history = AffiliateHistory::where('user_id', $user->id)
            ->where('commission_type', 'cpa')
            ->lockForUpdate()
            ->first();

        if (! $history) {
            return;
        }

        $history->increment('deposited_amount', $price);

        $sponsor = User::find($user->inviter);
        if (! $sponsor || $history->status !== 'pendente') {
            return;
        }

        if ($history->deposited_amount >= $sponsor->affiliate_baseline || $deposit->amount >= $sponsor->affiliate_baseline) {
            $sponsorWallet = Wallet::where('user_id', $history->inviter)->lockForUpdate()->first();
            if ($sponsorWallet) {
                $sponsorWallet->increment('refer_rewards', $sponsor->affiliate_cpa);
                $history->update(['status' => 1, 'commission_paid' => $sponsor->affiliate_cpa]);
            }
        }
    }
}
