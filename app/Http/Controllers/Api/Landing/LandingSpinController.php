<?php

namespace App\Http\Controllers\Api\Landing {

    use App\Helpers\Core as Helper;
    use App\Http\Controllers\Controller;
    use App\Models\SpinRuns;
    use App\Models\User;
    use Illuminate\Support\Str;

    class LandingSpinController extends Controller
    {
        /**
         * Prize table, loaded lazily (the constructor used to hit the database, which broke
         * `artisan route:list` and any boot without a configured spin table).
         */
        private function prizeTable(): array
        {
            return array_values((array) Helper::WheelPrizes());
        }

        public function Index()
        {
            return view('landingspin::index', [
                'prizes'      => $this->prizeTable(),
                'winners'     => $this->recentWinners(),
                'total_bonus' => $this->totalRedeemed(),
            ]);
        }

        /**
         * Server-side outcome. The client never chooses the prize; the result is signed with
         * the app key and can be redeemed exactly once at registration (see AuthController).
         */
        public function Spin()
        {
            $prizes = $this->prizeTable();
            if ($prizes === []) {
                return response()->json(['status' => false], 503);
            }

            $index = random_int(0, count($prizes) - 1); // was rand(0, count) -> could index past the end
            $data  = ['index' => $index, 'prize' => $prizes[$index]];
            $nonce = Str::uuid()->toString();
            $digest = hash_hmac('sha256', json_encode($data).$nonce, (string) config('app.key'));

            SpinRuns::create([
                'key'           => $digest,
                'possibilities' => json_encode($prizes),
                'nonce'         => $nonce,
                'prize'         => json_encode($data['prize']),
            ]);

            return response()->json([
                'status'    => true,
                'data'      => $data,
                'signature' => $digest,
                'nonce'     => $nonce,
            ], 200);
        }

        /**
         * Real redemptions only. This endpoint used to return 200 Faker-generated names and a
         * hardcoded "total bonus" figure, which is a deceptive promotional display.
         */
        public function Winners()
        {
            return response()->json([$this->recentWinners(), $this->totalRedeemed()]);
        }

        public function Prizes()
        {
            return response()->json(Helper::WheelPrizes());
        }

        private function recentWinners(): array
        {
            return SpinRuns::whereNotNull('redeemed_at')->latest('redeemed_at')->limit(50)->get()
                ->map(function (SpinRuns $run) {
                    $name = (string) User::whereKey($run->redeemed_by)->value('name');

                    return [
                        'name'     => $name !== '' ? Str::substr($name, 0, 1).'***' : '***',
                        'nivel'    => 'SPIN',
                        'prize'    => json_decode((string) $run->prize)->value ?? null,
                        'currency' => \Helper::getSetting()->currency_code ?? null,
                    ];
                })->all();
        }

        private function totalRedeemed(): float
        {
            return (float) SpinRuns::whereNotNull('redeemed_at')->get()
                ->sum(fn (SpinRuns $run) => (float) (json_decode((string) $run->prize)->value ?? 0));
        }
    }
}
