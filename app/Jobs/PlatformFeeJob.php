<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use App\Models\GameMatch;
use App\Models\UserBalance;
use App\Models\FinalSupport;
use App\Models\CoinTransaction;
use App\Models\User;

class PlatformFeeJob implements ShouldQueue
{
    use Queueable;

    protected $amount;
    protected $matchId;

    public function __construct($amount, $matchId)
    {
        $this->amount  = $amount;
        $this->matchId = $matchId;
    }

    public function handle(): void
    {
        DB::transaction(function () {

            $match = GameMatch::lockForUpdate()->find($this->matchId);
            if (!$match || !$match->winner_id) {
                return;
            }

            $winnerId = $match->winner_id;
            $loserId  = $winnerId == $match->player_one_id
                ? $match->player_two_id
                : $match->player_one_id;

            $adminId = 1;

            $amount = $this->amount;

            $winnerShare = 0;
            $loserShare  = 0;
            $adminShare  = 0;
            $referralPool = $amount * 0.01;

            if ($match->winner_percentage == 1 && $match->loser_percentage == 1) {
                $winnerShare = $amount * 0.02;
                $loserShare  = $amount * 0.01;
                $adminShare  = $amount * 0.11;

            } elseif ($match->winner_percentage == 1 && $match->loser_percentage == 0) {
                $winnerShare = $amount * 0.02;
                $adminShare  = $amount * 0.12;

            } elseif ($match->winner_percentage == 0 && $match->loser_percentage == 1) {
                $loserShare  = $amount * 0.01;
                $adminShare  = $amount * 0.13;

            } else {
                $adminShare  = $amount * 0.14;
            }

            $balanceIds = collect([$winnerId, $loserId, $adminId])->unique();

            $balances = UserBalance::whereIn('user_id', $balanceIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            if ($winnerShare > 0 && isset($balances[$winnerId])) {

                $balances[$winnerId]->total_balance += $winnerShare;
                $balances[$winnerId]->save();

                CoinTransaction::create([
                    'user_id'       => $winnerId,
                    'type'          => 'match',
                    'amount'        => $winnerShare,
                    'balance_after' => $balances[$winnerId]->total_balance,
                    'reference'     => 'Match Commission #' . $match->match_no,
                ]);
            }

            if ($loserShare > 0 && isset($balances[$loserId])) {

                $balances[$loserId]->total_balance += $loserShare;
                $balances[$loserId]->save();

                CoinTransaction::create([
                    'user_id'       => $loserId,
                    'type'          => 'match',
                    'amount'        => $loserShare,
                    'balance_after' => $balances[$loserId]->total_balance,
                    'reference'     => 'Match Commission #' . $match->match_no,
                ]);
            }

            $distributedReferral = 0;

            $betAmount = $winnerId == $match->player_one_id
                ? ($match->player_one_total - $match->player_one_bet)
                : ($match->player_two_total - $match->player_two_bet);

            if ($betAmount > 0 && $referralPool > 0) {

                $winningSupports = FinalSupport::where('match_id', $match->id)
                    ->where('supported_player_id', $winnerId)
                    ->get()
                    ->groupBy('user_id');

                foreach ($winningSupports as $userId => $supports) {

                    $user = User::lockForUpdate()->find($userId);

                    if (
                        !$user ||
                        !$user->referral_user_id ||
                        $user->reference_status == 1
                    ) {
                        continue;
                    }

                    $totalSupportAmount = $supports->sum('coin_amount');

                    $percentage = $totalSupportAmount / $betAmount;
                    $refAmount  = $referralPool * $percentage;

                    if ($refAmount <= 0) {
                        continue;
                    }

                    $refBalance = UserBalance::where('user_id', $user->referral_user_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$refBalance) {
                        continue;
                    }

                    $refBalance->total_balance += $refAmount;
                    $refBalance->save();

                    $distributedReferral += $refAmount;

                    CoinTransaction::create([
                        'user_id'       => $user->referral_user_id,
                        'type'          => 'referral',
                        'amount'        => $refAmount,
                        'balance_after' => $refBalance->total_balance,
                        'reference'     => 'Referral Match #' . $match->match_no,
                    ]);

                    $user->reference_status = 1;
                    $user->save();
                }
            }

            $adminFinal = $adminShare + ($referralPool - $distributedReferral);

            if (isset($balances[$adminId])) {

                $balances[$adminId]->total_balance += $adminFinal;
                $balances[$adminId]->save();

                CoinTransaction::create([
                    'user_id'       => $adminId,
                    'type'          => 'match',
                    'amount'        => $adminFinal,
                    'balance_after' => $balances[$adminId]->total_balance,
                    'reference'     => 'Platform Fee #' . $match->match_no,
                ]);
            }
        });
    }
}
