<?php

namespace App\Core\Analytics;

use App\Core\Money\Amount;
use App\Core\Tenancy\Tenancy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Operator KPIs computed from the ledger (the same source of truth reconciliation checks),
 * for the current tenant, per currency (never converted).
 *
 *  deposits     player-side credits of `deposit` journals
 *  withdrawals  player-side debits of `withdrawal.request` minus `withdrawal.cancel` credits
 *  ggr          net movement of platform:game (bets in - wins out) = gross gaming revenue
 *  bonus_cost   credits paid from platform:bonus
 *  active       distinct wallets with at least one `game.bet` journal
 */
final class Analytics
{
    public static function summary(Carbon $from, Carbon $to): array
    {
        $tid = Tenancy::requireId();
        $base = fn () => DB::table('ledger_entries as e')
            ->join('ledger_journals as j', 'j.id', '=', 'e.journal_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.account_id')
            ->where('e.tenant_id', $tid)
            ->whereBetween('j.created_at', [$from, $to]);

        $byCurrency = [];
        $put = function (string $metric, $rows) use (&$byCurrency) {
            foreach ($rows as $currency => $minor) {
                $byCurrency[$currency][$metric] = (int) $minor;
            }
        };

        $put('deposits', $base()->where('j.type', 'deposit')->where('a.kind', 'player')->groupBy('e.currency')->selectRaw('e.currency AS currency, SUM(e.amount_minor) AS v')->pluck('v', 'currency'));
        $put('withdrawals', $base()->whereIn('j.type', ['withdrawal.request', 'withdrawal.cancel'])->where('a.kind', 'player')
            ->groupBy('e.currency')->selectRaw('e.currency AS currency, -SUM(e.amount_minor) AS v')->pluck('v', 'currency'));
        $put('ggr', $base()->where('a.code', 'like', 'platform:game:%')->groupBy('e.currency')->selectRaw('e.currency AS currency, SUM(e.amount_minor) AS v')->pluck('v', 'currency'));
        $put('bonus_cost', $base()->where('a.code', 'like', 'platform:bonus:%')->groupBy('e.currency')->selectRaw('e.currency AS currency, -SUM(e.amount_minor) AS v')->pluck('v', 'currency'));
        $put('active_players', $base()->where('j.type', 'game.bet')->where('a.kind', 'player')
            ->groupBy('e.currency')->selectRaw('e.currency AS currency, COUNT(DISTINCT a.wallet_id) AS v')->pluck('v', 'currency'));

        $out = [];
        foreach ($byCurrency as $currency => $m) {
            $out[$currency] = [
                'deposits'       => Amount::fromMinor($m['deposits'] ?? 0),
                'withdrawals'    => Amount::fromMinor($m['withdrawals'] ?? 0),
                'ggr'            => Amount::fromMinor($m['ggr'] ?? 0),
                'bonus_cost'     => Amount::fromMinor($m['bonus_cost'] ?? 0),
                'active_players' => (int) ($m['active_players'] ?? 0),
            ];
        }
        ksort($out);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'registrations' => User::query()->whereBetween('created_at', [$from, $to])->count(),
            'currencies' => $out,
        ];
    }

    /** Daily deposits and GGR for the last $days days (per currency). */
    public static function daily(int $days): array
    {
        $tid = Tenancy::requireId();
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = DB::table('ledger_entries as e')
            ->join('ledger_journals as j', 'j.id', '=', 'e.journal_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.account_id')
            ->where('e.tenant_id', $tid)->where('j.created_at', '>=', $from)
            ->where(fn ($q) => $q->where(fn ($d) => $d->where('j.type', 'deposit')->where('a.kind', 'player'))->orWhere('a.code', 'like', 'platform:game:%'))
            ->selectRaw("DATE(j.created_at) AS day, e.currency, CASE WHEN a.code LIKE 'platform:game:%' THEN 'ggr' ELSE 'deposits' END AS metric, SUM(e.amount_minor) AS minor")
            ->groupBy('day', 'e.currency', 'metric')->get();

        $series = [];
        foreach ($rows as $r) {
            $series[$r->day][$r->currency][$r->metric] = Amount::fromMinor((int) $r->minor);
        }
        ksort($series);

        return $series;
    }
}
