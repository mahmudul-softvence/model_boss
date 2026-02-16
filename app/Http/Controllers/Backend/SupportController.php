<?php

namespace App\Http\Controllers\Backend;

use App\Events\SupportPlaced;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\GameMatch;
use App\Models\Support;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'match_id'            => 'required|exists:game_matches,id',
            'supported_player_id' => 'required|exists:users,id',
            'coin_amount'         => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {

            $responseData = DB::transaction(function () use ($request) {

                $user = auth('api')->user();

                $balance = UserBalance::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $match = GameMatch::lockForUpdate()
                    ->findOrFail($request->match_id);

                if ($balance->total_balance < $request->coin_amount) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Insufficient balance',
                    ], 400);
                }

                $balance->total_balance -= $request->coin_amount;
                $balance->total_bet += $request->coin_amount;
                $balance->save();

                $support = Support::create([
                    'match_id'            => $match->id,
                    'match_no'            => $match->match_no,
                    'supported_player_id' => $request->supported_player_id,
                    'user_id'             => $user->id,
                    'coin_amount'         => $request->coin_amount,
                    'result'              => 'pending',
                ]);

                if ($request->supported_player_id == $match->player_one_id) {

                    $match->player_one_total += $request->coin_amount;

                } elseif ($request->supported_player_id == $match->player_two_id) {

                    $match->player_two_total += $request->coin_amount;

                } else {
                    throw new \Exception('Invalid supported player');
                }

                $match->save();

                CoinTransaction::create([
                    'user_id'       => $user->id,
                    'type'          => 'support',
                    'amount'        => -$request->coin_amount,
                    'balance_after' => $balance->total_balance,
                    'reference'     => 'Support for match #' . $match->match_no,
                ]);

                $topSupporters = Support::where('match_id', $match->id)
                    ->with('supporter:id,name')
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
                    'support' => $support,
                    'updated_balance' => $balance->total_balance,
                    'updated_total_bet' => $balance->total_bet,
                    'match_player_one_total' => $match->player_one_total,
                    'match_player_two_total' => $match->player_two_total,
                    'top_supporters' => $topSupporters,
                ];
            });

            event(new SupportPlaced($responseData, $request->match_id));

            return response()->json([
                'status'  => true,
                'message' => 'Support placed successfully',
                'data'    => $responseData,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


}
