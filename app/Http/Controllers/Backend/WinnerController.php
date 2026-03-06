<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserBalance;
use App\Models\FinalSupport;
use App\Models\CoinTransaction;
use App\Jobs\PlatformFeeJob;

class WinnerController extends Controller
{

    public function winner(Request $request, $id)
    {
        try {

            DB::transaction(function () use ($request, $id) {

                $match = GameMatch::lockForUpdate()->find($id);

                if (!$match) {
                    throw new \RuntimeException('Match not found');
                }

                if ($match->confirmation_status != 1) {
                    throw new \RuntimeException('Match is not confirmed');
                }

                if ($match->winner_id) {
                    throw new \RuntimeException('Winner has already been declared');
                }

                $request->validate([
                    'winner_id' => [
                        'required',
                        'exists:users,id',
                        Rule::in([$match->player_one_id, $match->player_two_id]),
                    ],
                ]);

                $winnerId = $request->winner_id;

                $match->update([
                    'winner_id' => $winnerId,
                    'status'    => 'completed',
                ]);

                $totalWin = $winnerId == $match->player_one_id
                    ? ($match->player_one_total - $match->player_one_bet) * 2
                    : ($match->player_two_total - $match->player_two_bet) * 2;

                $platformFee = $totalWin * 0.15;

                DB::afterCommit(function () use ($platformFee, $match) {
                    PlatformFeeJob::dispatch($platformFee, $match->id);
                });

                $supports = FinalSupport::where('match_id', $match->id)->get();

                if ($supports->isEmpty()) {
                    return;
                }

                $userIds = $supports->pluck('user_id')->unique();

                $balances = UserBalance::whereIn('user_id', $userIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('user_id');

                foreach ($supports as $support) {

                    $userBalance = $balances->get($support->user_id);

                    if (!$userBalance) {
                        continue;
                    }

                    if ($support->supported_player_id == $winnerId) {

                        $gross = $support->coin_amount * 2;
                        $net   = $gross - ($gross * 0.15);

                        $userBalance->total_balance += $net;
                        $userBalance->total_earning += $net;
                        $userBalance->save();

                        CoinTransaction::create([
                            'user_id'       => $support->user_id,
                            'type'          => 'win',
                            'amount'        => $net,
                            'balance_after' => $userBalance->total_balance,
                            'reference'     => 'Match Win #' . $match->match_no,
                        ]);

                        $support->update(['result' => 'win']);

                    } else {

                        CoinTransaction::create([
                            'user_id'       => $support->user_id,
                            'type'          => 'loss',
                            'amount'        => $support->coin_amount,
                            'balance_after' => $userBalance->total_balance,
                            'reference'     => 'Match Lose #' . $match->match_no,
                        ]);

                        $support->update(['result' => 'loss']);
                    }
                }
            });

            return response()->json([
                'status'  => true,
                'message' => 'Winner declared and payouts processed successfully'
            ], 200);

        } catch (\RuntimeException $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }

    public function userTransactions(Request $request)
    {
        $userId = auth('api')->id();
        $perPage = $request->per_page ?? 10;

        $transactions = CoinTransaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Transactions retrieved successfully',
            'data'   => $transactions->items(),
            'meta'   => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
            ],
        ], 200);
    }

}
