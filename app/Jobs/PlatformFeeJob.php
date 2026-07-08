<?php

namespace App\Jobs;

use App\Models\CoinTransaction;
use App\Models\FinalSupport;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PlatformFeeJob implements ShouldQueue
{
    use Queueable;

    private const ADMIN_USER_ID = 1;

    public function __construct(
        public float $amount,
        public int $matchId,
    ) {}

    /**
     * Split the platform fee between the winner, loser, referrers, and admin.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $match = GameMatch::lockForUpdate()->find($this->matchId);

            if (! $match || ! $match->winner_id) {
                return;
            }

            $winnerId = $match->winner_id;
            $loserId = $winnerId == $match->player_one_id
                ? $match->player_two_id
                : $match->player_one_id;

            $shares = $this->calculateShares($match);

            $balances = UserBalance::whereIn(
                'user_id',
                collect([$winnerId, $loserId, self::ADMIN_USER_ID])->unique(),
            )
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            if ($shares['winner'] > 0) {
                $this->creditMatchEarnings(
                    $balances->get($winnerId),
                    $shares['winner'],
                    'Match Commission #'.$match->match_no,
                );
            }

            if ($shares['loser'] > 0) {
                $this->creditMatchEarnings(
                    $balances->get($loserId),
                    $shares['loser'],
                    'Match Commission #'.$match->match_no,
                );
            }

            $distributedReferral = $this->distributeReferralPool($match, $shares['referral_pool']);

            $this->creditMatchEarnings(
                $balances->get(self::ADMIN_USER_ID),
                $shares['admin'] + ($shares['referral_pool'] - $distributedReferral),
                'Platform Fee #'.$match->match_no,
            );
        });
    }

    /**
     * Split the fee into fifteenths based on which players opted into commission.
     *
     * @return array{winner: float, loser: float, referral_pool: float, admin: float}
     */
    private function calculateShares(GameMatch $match): array
    {
        $winnerOptedIn = $match->winner_percentage == 1;
        $loserOptedIn = $match->loser_percentage == 1;

        $winnerFifteenths = $winnerOptedIn ? 2 : 0;
        $loserFifteenths = $loserOptedIn ? 1 : 0;
        $referralFifteenths = 1;
        $adminFifteenths = 15 - $winnerFifteenths - $loserFifteenths - $referralFifteenths;

        return [
            'winner' => $this->amount * ($winnerFifteenths / 15),
            'loser' => $this->amount * ($loserFifteenths / 15),
            'referral_pool' => $this->amount * ($referralFifteenths / 15),
            'admin' => $this->amount * ($adminFifteenths / 15),
        ];
    }

    /**
     * Add match earnings to a balance and record the coin transaction.
     */
    private function creditMatchEarnings(?UserBalance $balance, float $amount, string $reference): void
    {
        if (! $balance) {
            return;
        }

        $balance->total_balance += $amount;
        $balance->total_earning += $amount;
        $balance->save();

        CoinTransaction::create([
            'user_id' => $balance->user_id,
            'type' => 'match',
            'amount' => $amount,
            'balance_after' => $balance->total_balance,
            'reference' => $reference,
        ]);
    }

    /**
     * Pay referrers of first-time winning supporters proportionally to their
     * share of the winning bet pool. Returns the total amount paid out.
     */
    private function distributeReferralPool(GameMatch $match, float $referralPool): float
    {
        $betAmount = $match->winner_id == $match->player_one_id
            ? ($match->player_one_total - $match->player_one_bet)
            : ($match->player_two_total - $match->player_two_bet);

        if ($betAmount <= 0 || $referralPool <= 0) {
            return 0;
        }

        $distributed = 0;

        $winningSupports = FinalSupport::where('match_id', $match->id)
            ->where('supported_player_id', $match->winner_id)
            ->get()
            ->groupBy('user_id');

        foreach ($winningSupports as $userId => $supports) {
            $user = User::lockForUpdate()->find($userId);

            if (! $user || ! $user->referral_user_id || $user->reference_status == 1) {
                continue;
            }

            $refAmount = $referralPool * ($supports->sum('coin_amount') / $betAmount);

            if ($refAmount <= 0) {
                continue;
            }

            $refBalance = UserBalance::where('user_id', $user->referral_user_id)
                ->lockForUpdate()
                ->first();

            if (! $refBalance) {
                continue;
            }

            $refBalance->total_balance += $refAmount;
            $refBalance->total_referral_earning += $refAmount;
            $refBalance->total_earning += $refAmount;
            $refBalance->save();

            CoinTransaction::create([
                'user_id' => $user->referral_user_id,
                'type' => 'referral',
                'amount' => $refAmount,
                'balance_after' => $refBalance->total_balance,
                'reference' => 'Referral Match #'.$match->match_no,
            ]);

            $user->update(['reference_status' => 1]);

            $distributed += $refAmount;
        }

        return $distributed;
    }
}
