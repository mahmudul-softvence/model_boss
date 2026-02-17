<?php

namespace App\Http\Controllers\Backend;

use App\Events\SupportPlaced;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\FinalSupport;
use App\Models\GameMatch;
use App\Models\Support;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'match_id'            => 'required|exists:game_matches,id',
            'supported_player_id' => 'required|exists:users,id',
            'coin_amount'         => 'required|numeric|min:1',
        ]);

        try {

            $responseData = DB::transaction(function () use ($request) {

                $user = auth('api')->user();

                $balance = UserBalance::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $match = GameMatch::lockForUpdate()
                    ->findOrFail($request->match_id);

                // Match must be open
                if ($match->confirmation_status !== 0) {
                    abort(400, 'Match is not open for support. Time is over.');
                }

                // Check balance
                if ($balance->total_balance < $request->coin_amount) {
                    abort(400, 'Insufficient balance');
                }

                // Deduct balance
                $balance->decrement('total_balance', $request->coin_amount);
                $balance->increment('total_bet', $request->coin_amount);

                // Validate supported player
                if (!in_array($request->supported_player_id, [
                    $match->player_one_id,
                    $match->player_two_id
                ])) {
                    abort(400, 'Invalid supported player');
                }

                // Create support
                $support = Support::create([
                    'match_id'            => $match->id,
                    'match_no'            => $match->match_no,
                    'supported_player_id' => $request->supported_player_id,
                    'user_id'             => $user->id,
                    'coin_amount'         => $request->coin_amount,
                    'result'              => 'pending',
                ]);

                // Update match totals
                if ($request->supported_player_id == $match->player_one_id) {
                    $match->increment('player_one_total', $request->coin_amount);
                } else {
                    $match->increment('player_two_total', $request->coin_amount);
                }

                // Log transaction
                CoinTransaction::create([
                    'user_id'       => $user->id,
                    'type'          => 'support',
                    'amount'        => -$request->coin_amount,
                    'balance_after' => $balance->fresh()->total_balance,
                    'reference'     => 'Support for match #' . $match->match_no,
                ]);

                $topSupporters = Support::with('supporter:id,name')
                    ->orderByDesc('coin_amount')
                    ->get()
                    ->groupBy('user_id')
                    ->sortByDesc(function ($userSupports) {

                        return $userSupports->sum('coin_amount');
                    })
                    ->take(10)
                    ->values()
                    ->map(function ($userSupports, $index) {
                        return [
                            'user_id' => $userSupports[0]->user_id,
                            'serial_no' => str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                            'supported_amounts' => $userSupports->pluck('coin_amount')->implode(', '),
                            'supporter' => [
                                'id' => $userSupports[0]->supporter->id,
                                'name' => $userSupports[0]->supporter->name,
                            ],
                        ];
                    });

                return [
                    'support'                    => $support,
                    'updated_balance'            => $balance->fresh()->total_balance,
                    'updated_total_bet'          => $balance->fresh()->total_bet,
                    'match_player_one_total'     => $match->fresh()->player_one_total,
                    'match_player_two_total'     => $match->fresh()->player_two_total,
                    'top_supporters'             => $topSupporters,
                ];
            });

            event(new SupportPlaced($responseData, $request->match_id));

            return response()->json([
                'status'  => true,
                'message' => 'Support placed successfully',
                'data'    => $responseData,
            ], 200);

        } catch (HttpException $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    // public function confirm(Request $request, $id)
    // {
    //     $request->validate([
    //         'confirmation_status' => 'required|in:1,2',
    //     ]);

    //     try {

    //         $match = DB::transaction(function () use ($request, $id) {

    //             $match = GameMatch::lockForUpdate()->findOrFail($id);

    //             if ($match->confirmation_status !== 0) {
    //                 abort(400, 'Match has already been confirmed or declined.');
    //             }

    //             $match->confirmation_status = $request->confirmation_status;
    //             $match->save();

    //             $supports = Support::where('match_id', $match->id)
    //                 ->lockForUpdate()
    //                 ->orderBy('id')
    //                 ->get();

    //             if ($request->confirmation_status == 1) {
    //                 $p1_total = $match->player_one_total;
    //                 $p2_total = $match->player_two_total;

    //                 if ($p1_total != $p2_total) {
    //                     $bigger_player_id = $p1_total > $p2_total ? $match->player_one_id : $match->player_two_id;
    //                     $bigger_total = max($p1_total, $p2_total);
    //                     $smaller_total = min($p1_total, $p2_total);
    //                     $excess = $bigger_total - $smaller_total;

    //                     $bigger_supports = $supports->where('supported_player_id', $bigger_player_id)->reverse();

    //                     foreach ($bigger_supports as $support) {
    //                         if ($excess <= 0) break;

    //                         $userBalance = UserBalance::where('user_id', $support->user_id)->lockForUpdate()->firstOrFail();
    //                         $refund_amount = min($support->coin_amount, $excess);

    //                         $userBalance->total_balance += $refund_amount;
    //                         $userBalance->save();

    //                         CoinTransaction::create([
    //                             'user_id'       => $support->user_id,
    //                             'type'          => 'support-return',
    //                             'amount'        => $refund_amount,
    //                             'balance_after' => $userBalance->total_balance,
    //                             'reference'     => "Refund support for match #{$match->match_no}",
    //                         ]);

    //                         $excess -= $refund_amount;
    //                     }

    //                     $match->player_one_total = $smaller_total;
    //                     $match->player_two_total = $smaller_total;
    //                     $match->save();
    //                 }

    //             } elseif ($request->confirmation_status == 2) {
    //                 $match->player_one_total = 0;
    //                 $match->player_two_total = 0;
    //                 $match->save();

    //                 foreach ($supports as $support) {
    //                     $userBalance = UserBalance::where('user_id', $support->user_id)->lockForUpdate()->firstOrFail();

    //                     $userBalance->total_balance += $support->coin_amount;
    //                     $userBalance->save();

    //                     CoinTransaction::create([
    //                         'user_id'       => $support->user_id,
    //                         'type'          => 'support-return',
    //                         'amount'        => $support->coin_amount,
    //                         'balance_after' => $userBalance->total_balance,
    //                         'reference'     => "Refund support for declined match #{$match->match_no}",
    //                     ]);
    //                 }
    //             }

    //             return $match;
    //         });

    //         return response()->json([
    //             'status'  => true,
    //             'message' => 'Match confirmation status updated and balances adjusted successfully.',
    //             'data'    => [
    //                 'match_id'            => $match->id,
    //                 'confirmation_status' => $match->confirmation_status,
    //                 'player_one_total'    => $match->player_one_total,
    //                 'player_two_total'    => $match->player_two_total,
    //             ],
    //         ], 200);

    //     } catch (HttpException $e) {

    //         return response()->json([
    //             'status'  => false,
    //             'message' => $e->getMessage(),
    //         ], $e->getStatusCode());

    //     } catch (\Throwable $e) {

    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Something went wrong',
    //         ], 500);
    //     }
    // }

    public function confirm(Request $request, $id)
    {
        $request->validate([
            'confirmation_status' => 'required|in:1,2',
        ]);

        try {

            $match = DB::transaction(function () use ($request, $id) {

                $match = GameMatch::lockForUpdate()->findOrFail($id);

                if ((int) $match->confirmation_status !== 0) {
                    abort(400, 'Match has already been confirmed or declined.');
                }

                $match->confirmation_status = $request->confirmation_status;
                $match->save();

                $supports = Support::where('match_id', $match->id)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                if ($request->confirmation_status == 1) {

                    $p1_total = $match->player_one_total;
                    $p2_total = $match->player_two_total;

                    $remainingAmounts = [];

                    foreach ($supports as $support) {
                        $remainingAmounts[$support->id] = $support->coin_amount;
                    }

                    if ($p1_total != $p2_total) {

                        $bigger_player_id = $p1_total > $p2_total
                            ? $match->player_one_id
                            : $match->player_two_id;

                        $bigger_total  = max($p1_total, $p2_total);
                        $smaller_total = min($p1_total, $p2_total);
                        $excess        = $bigger_total - $smaller_total;

                        $bigger_supports = $supports
                            ->where('supported_player_id', $bigger_player_id)
                            ->reverse();

                        foreach ($bigger_supports as $support) {

                            if ($excess <= 0) break;

                            $refund_amount = min($support->coin_amount, $excess);

                            $userBalance = UserBalance::where('user_id', $support->user_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $userBalance->total_balance += $refund_amount;
                            $userBalance->save();

                            CoinTransaction::create([
                                'user_id'       => $support->user_id,
                                'type'          => 'support-return',
                                'amount'        => $refund_amount,
                                'balance_after' => $userBalance->total_balance,
                                'reference'     => "Refund support for match #{$match->match_no}",
                            ]);

                            $remainingAmounts[$support->id] -= $refund_amount;

                            $excess -= $refund_amount;
                        }

                        $match->update([
                            'player_one_total' => $smaller_total,
                            'player_two_total' => $smaller_total,
                        ]);
                    }

                    foreach ($supports as $support) {

                        $effectiveAmount = $remainingAmounts[$support->id] ?? 0;

                        if ($effectiveAmount <= 0) {
                            continue;
                        }

                        FinalSupport::create([
                            'match_id'            => $match->id,
                            'match_no'            => $match->match_no,
                            'supported_player_id' => $support->supported_player_id,
                            'user_id'             => $support->user_id,
                            'coin_amount'         => $effectiveAmount,
                            'result'              => 'pending',
                        ]);
                    }
                }

                elseif ($request->confirmation_status == 2) {

                    $match->update([
                        'player_one_total' => 0,
                        'player_two_total' => 0,
                    ]);

                    foreach ($supports as $support) {

                        $userBalance = UserBalance::where('user_id', $support->user_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $userBalance->total_balance += $support->coin_amount;
                        $userBalance->save();

                        CoinTransaction::create([
                            'user_id'       => $support->user_id,
                            'type'          => 'support-return',
                            'amount'        => $support->coin_amount,
                            'balance_after' => $userBalance->total_balance,
                            'reference'     => "Refund support for declined match #{$match->match_no}",
                        ]);
                    }

                }

                return $match;
            });

            return response()->json([
                'status'  => true,
                'message' => 'Match confirmation status updated and balances adjusted successfully.',
                'data'    => [
                    'match_id'            => $match->id,
                    'confirmation_status' => $match->confirmation_status,
                    'player_one_total'    => $match->player_one_total,
                    'player_two_total'    => $match->player_two_total,
                ],
            ], 200);

        } catch (HttpException $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

}
