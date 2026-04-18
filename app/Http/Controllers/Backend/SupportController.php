<?php

namespace App\Http\Controllers\Backend;

use App\Events\SupportPlaced;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\FinalSupport;
use App\Models\GameMatch;
use App\Models\Referral;
use App\Models\Support;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:game_matches,id',
            'supported_player_id' => 'required|exists:users,id',
            'coin_amount' => 'required|numeric|min:1',
        ]);

        try {

            $responseData = DB::transaction(function () use ($request) {

                $user = auth('api')->user();

                $balance = UserBalance::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $match = GameMatch::lockForUpdate()
                    ->findOrFail($request->match_id);

                if ($match->confirmation_status !== 0) {
                    abort(400, 'Match is not open for support. Time is over.');
                }

                if ($balance->total_balance < $request->coin_amount) {
                    abort(400, 'Insufficient balance');
                }

                $balance->decrement('total_balance', $request->coin_amount);
                $balance->increment('total_bet', $request->coin_amount);

                if (! in_array($request->supported_player_id, [
                    $match->player_one_id,
                    $match->player_two_id,
                ])) {
                    abort(400, 'Invalid supported player');
                }

                $support = Support::create([
                    'match_id' => $match->id,
                    'match_no' => $match->match_no,
                    'supported_player_id' => $request->supported_player_id,
                    'user_id' => $user->id,
                    'coin_amount' => $request->coin_amount,
                    'result' => 'pending',
                ]);

                if ($request->supported_player_id == $match->player_one_id) {
                    $match->increment('player_one_total', $request->coin_amount);
                } else {
                    $match->increment('player_two_total', $request->coin_amount);
                }

                CoinTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'support',
                    'amount' => -$request->coin_amount,
                    'balance_after' => $balance->fresh()->total_balance,
                    'reference' => 'Support for match #'.$match->match_no,
                ]);

                // Existing top supporters logic
                $topUsers = Support::selectRaw('user_id, SUM(coin_amount) as total_amount')
                    ->where('match_id', $match->id)
                    ->groupBy('user_id')
                    ->orderByDesc('total_amount')
                    ->limit(10)
                    ->pluck('user_id');

                $topSupporters = Support::with('supporter:id,name,image')
                    ->where('match_id', $match->id)
                    ->whereIn('user_id', $topUsers)
                    ->get()
                    ->groupBy('user_id')
                    ->sortByDesc(fn ($userSupports) => $userSupports->sum('coin_amount'))
                    ->values()
                    ->map(function ($userSupports, $index) {

                        $sortedSupports = $userSupports->sortByDesc('coin_amount')->values();
                        $first = $sortedSupports->first();

                        return [
                            'user_id' => $first->user_id,
                            'serial_no' => str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                            'supported_amounts' => $sortedSupports
                                ->pluck('coin_amount')
                                ->implode(', '),
                            'supporter' => [
                                'id' => $first->supporter->id,
                                'name' => $first->supporter->name,
                                'image' => optional($first->supporter)->image_url,
                            ],
                        ];
                    });

                $playerOneSupport = Support::with('supporter')
                    ->where('match_id', $match->id)
                    ->where('supported_player_id', $match->player_one_id)
                    ->select('user_id', DB::raw('SUM(coin_amount) as total_amount'))
                    ->groupBy('user_id')
                    ->orderByDesc('total_amount')
                    ->first();

                $playerTwoSupport = Support::with('supporter')
                    ->where('match_id', $match->id)
                    ->where('supported_player_id', $match->player_two_id)
                    ->select('user_id', DB::raw('SUM(coin_amount) as total_amount'))
                    ->groupBy('user_id')
                    ->orderByDesc('total_amount')
                    ->first();

                $playerOneTopSupporter = $playerOneSupport && $playerOneSupport->supporter
                    ? [
                        'id' => $playerOneSupport->supporter->id,
                        'name' => $playerOneSupport->supporter->name,
                        'image' => $playerOneSupport->supporter->image
                            ? asset('storage/'.$playerOneSupport->supporter->image)
                            : null,
                    ]
                    : null;

                $playerTwoTopSupporter = $playerTwoSupport && $playerTwoSupport->supporter
                    ? [
                        'id' => $playerTwoSupport->supporter->id,
                        'name' => $playerTwoSupport->supporter->name,
                        'image' => $playerTwoSupport->supporter->image
                            ? asset('storage/'.$playerTwoSupport->supporter->image)
                            : null,
                    ]
                    : null;

                $playerOneTotalSupporter = Support::where('match_id', $match->id)
                    ->where('supported_player_id', $match->player_one_id)
                    ->count();
                $playerTwoTotalSupporter = Support::where('match_id', $match->id)
                    ->where('supported_player_id', $match->player_two_id)
                    ->count();

                return [
                    'support' => $support,
                    'updated_balance' => $balance->fresh()->total_balance,
                    'updated_total_bet' => $balance->fresh()->total_bet,
                    'match_player_one_total' => $match->fresh()->player_one_total,
                    'match_player_two_total' => $match->fresh()->player_two_total,
                    'top_supporters' => $topSupporters,
                    'player_one_top_supporter' => $playerOneTopSupporter,
                    'player_one_total_supporter' => $playerOneTotalSupporter,
                    'player_two_top_supporter' => $playerTwoTopSupporter,
                    'player_two_total_supporter' => $playerTwoTotalSupporter,
                ];
            });

            event(new SupportPlaced($responseData, $request->match_id));

            return response()->json([
                'status' => true,
                'message' => 'Support placed successfully',
                'data' => $responseData,
            ], 200);

        } catch (HttpException $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

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

                        $bigger_total = max($p1_total, $p2_total);
                        $smaller_total = min($p1_total, $p2_total);
                        $excess = $bigger_total - $smaller_total;

                        $bigger_supports = $supports
                            ->where('supported_player_id', $bigger_player_id)
                            ->reverse();

                        foreach ($bigger_supports as $support) {

                            if ($excess <= 0) {
                                break;
                            }

                            $refund_amount = min($support->coin_amount, $excess);

                            $userBalance = UserBalance::where('user_id', $support->user_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $userBalance->total_balance += $refund_amount;
                            $userBalance->save();

                            CoinTransaction::create([
                                'user_id' => $support->user_id,
                                'type' => 'support-return',
                                'amount' => $refund_amount,
                                'balance_after' => $userBalance->total_balance,
                                'reference' => "Refund support for match #{$match->match_no}",
                            ]);

                            $remainingAmounts[$support->id] -= $refund_amount;

                            $excess -= $refund_amount;
                        }

                        $match->update([
                            'player_one_total' => $smaller_total,
                            'player_two_total' => $smaller_total,
                            'type' => 'live',
                        ]);
                    }

                    foreach ($supports as $support) {

                        $effectiveAmount = $remainingAmounts[$support->id] ?? 0;

                        if ($effectiveAmount <= 0) {
                            continue;
                        }

                        FinalSupport::create([
                            'support_id' => $support->id,
                            'match_id' => $match->id,
                            'match_no' => $match->match_no,
                            'supported_player_id' => $support->supported_player_id,
                            'user_id' => $support->user_id,
                            'coin_amount' => $effectiveAmount,
                            'result' => 'pending',
                        ]);
                    }
                } elseif ($request->confirmation_status == 2) {

                    $match->update([
                        'player_one_total' => 0,
                        'player_two_total' => 0,
                        'type' => 'unsettled',
                    ]);

                    foreach ($supports as $support) {

                        $userBalance = UserBalance::where('user_id', $support->user_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $userBalance->total_balance += $support->coin_amount;
                        $userBalance->save();

                        CoinTransaction::create([
                            'user_id' => $support->user_id,
                            'type' => 'support-return',
                            'amount' => $support->coin_amount,
                            'balance_after' => $userBalance->total_balance,
                            'reference' => "Refund support for declined match #{$match->match_no}",
                        ]);
                    }

                }

                return $match;
            });

            return response()->json([
                'status' => true,
                'message' => 'Match confirmation status updated and balances adjusted successfully.',
                'data' => [
                    'match_id' => $match->id,
                    'confirmation_status' => $match->confirmation_status,
                    'player_one_total' => $match->player_one_total,
                    'player_two_total' => $match->player_two_total,
                ],
            ], 200);

        } catch (HttpException $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    public function pastSupport(Request $request)
    {
        $userId = auth('api')->id();
        $perPage = $request->per_page ?? 10;
        $page = $request->page ?? 1;

        $supports = FinalSupport::with(['match.game', 'supportedPlayer'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(['match_id', 'supported_player_id']);

        $data = [];
        $rankNo = 1;

        foreach ($supports as $matchId => $playerGroups) {
            foreach ($playerGroups as $playerId => $playerSupports) {

                $rangePoints = $playerSupports->pluck('coin_amount')
                    ->sortDesc()
                    ->map(fn ($v) => number_format($v, 2))
                    ->values();

                $firstSupport = $playerSupports->first();

                $data[] = [
                    'rank_no' => str_pad($rankNo, 3, '0', STR_PAD_LEFT),
                    'game_name' => $firstSupport->match->game->name ?? null,
                    'match_no' => $firstSupport->match->match_no,
                    'supported_player' => $firstSupport->supportedPlayer->name ?? null,
                    'result' => $firstSupport->result,
                    'range_points' => $rangePoints->toArray(),
                ];

                $rankNo++;
            }
        }

        $collection = collect($data);

        $paginated = new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );

        return response()->json([
            'status' => true,
            'message' => 'Past supports retrieved successfully.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'prev' => $paginated->currentPage() > 1,
                'next' => $paginated->hasMorePages(),
            ],
        ], 200);
    }

    public function referralLinkUsed(Request $request)
    {
        $userId = auth('api')->id();
        $perPage = $request->per_page ?? 10;

        $referralUserIds = Referral::where('user_id', $userId)
            ->pluck('referral_user_id');

        $users = User::whereIn('id', $referralUserIds)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $finalSupports = FinalSupport::whereIn('user_id', $users->pluck('id'))
            ->orderByDesc('coin_amount')
            ->get()
            ->groupBy('user_id');

        $data = [];
        $rankStart = ($users->currentPage() - 1) * $users->perPage() + 1;

        foreach ($users as $index => $user) {

            $userSupports = $finalSupports[$user->id] ?? collect();

            $rangePoints = $userSupports->pluck('coin_amount')
                ->map(fn ($v) => number_format($v, 2))
                ->values()
                ->toArray();

            $data[] = [
                'rank_no' => str_pad($rankStart + $index, 3, '0', STR_PAD_LEFT),
                'user_name' => $user->name,
                'range_points' => $rangePoints,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Referral users data retrieved successfully.',
            'data' => $data,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'prev' => $users->currentPage() > 1,
                'next' => $users->hasMorePages(),
            ],
        ], 200);
    }

    public function supportHistory(Request $request)
    {
        $userId = auth('api')->id();
        $filter = $request->type ?? 'all';
        $perPage = $request->per_page ?? 10;
        $page = $request->page ?? 1;

        $supports = Support::with([
            'match.playerOne:id,name,image',
            'match.playerTwo:id,name,image',
        ])
            ->where('user_id', $userId)
            ->whereHas('match', function ($q) use ($filter) {

                if ($filter === 'live') {
                    $q->where('confirmation_status', 1)
                        ->where('type', 'live');
                }

                if ($filter === 'settled') {
                    $q->where('confirmation_status', 1)
                        ->where('type', 'completed');
                }

                if ($filter === 'unsettled') {
                    $q->where('confirmation_status', 2);
                }

                // all → no condition
            })
            ->latest()
            ->get();

        $finalSupports = FinalSupport::where('user_id', $userId)
            ->get()
            ->keyBy('support_id');

        $data = $supports->map(function ($support) use ($finalSupports) {

            $match = $support->match;

            if (! $match) {
                return null;
            }

            $coinAmount = $support->coin_amount;
            $result = $support->result;

            if ($match->confirmation_status == 1 && isset($finalSupports[$support->id])) {
                $coinAmount = $finalSupports[$support->id]->coin_amount;
                $result = $finalSupports[$support->id]->result;
            }

            return [
                'match_id' => $match->id,
                'match_no' => $match->match_no,
                'match_date' => $match->match_date,
                'match_time' => $match->match_time,
                'type' => $match->type,
                'confirmation_status' => $match->confirmation_status,

                'player_one' => [
                    'name' => optional($match->playerOne)->name,
                    'image' => optional($match->playerOne)->image,
                ],

                'player_two' => [
                    'name' => optional($match->playerTwo)->name,
                    'image' => optional($match->playerTwo)->image,
                ],

                'coin_amount' => $coinAmount,
                'result' => $result,
            ];
        })->filter()->values();

        $paginated = new LengthAwarePaginator(
            $data->forPage($page, $perPage)->values(),
            $data->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $request->query()]
        );

        return response()->json([
            'status' => true,
            'message' => 'Support history retrieved successfully.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'prev' => $paginated->currentPage() > 1,
                'next' => $paginated->hasMorePages(),
            ],
        ]);
    }

    public function bigBossSupporter()
    {
        $topUsers = Support::selectRaw('user_id, SUM(coin_amount) as total_amount')
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->pluck('user_id');

        $topSupporters = Support::with('supporter:id,name,image')
            ->whereIn('user_id', $topUsers)
            ->get()
            ->groupBy('user_id')
            ->sortByDesc(fn ($userSupports) => $userSupports->sum('coin_amount'))
            ->values()
            ->map(function ($userSupports, $index) {

                $sortedSupports = $userSupports->sortByDesc('coin_amount')->values();
                $first = $sortedSupports->first();

                return [
                    'user_id' => $first->user_id,
                    'serial_no' => str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'supported_amounts' => $sortedSupports
                        ->pluck('coin_amount')
                        ->implode(', '),
                    'supporter' => [
                        'id' => $first->supporter->id,
                        'name' => $first->supporter->name,
                        'image' => optional($first->supporter)->image_url,
                    ],
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Big Boss Supporter retrieved successfully.',
            'data' => $topSupporters,
        ], 200);
    }
}
