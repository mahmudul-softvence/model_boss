<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\MatchForVoting;
use App\Models\MatchVoter;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchForVotingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $query = MatchForVoting::with([
            'game:id,name,image',
            'playerOne:id,name,image',
            'playerTwo:id,name,image',
        ]);

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->game_id);
        }

        if ($request->filled('player_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('player_one_id', $request->player_id)
                    ->orWhere('player_two_id', $request->player_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('id', 'like', "%{$search}%")

                    ->orWhereHas('game', function ($gameQuery) use ($search) {
                        $gameQuery->where('name', 'like', "%{$search}%");
                    })

                    ->orWhereHas('playerOne', function ($playerQuery) use ($search) {
                        $playerQuery->where('name', 'like', "%{$search}%");
                    })

                    ->orWhereHas('playerTwo', function ($playerQuery) use ($search) {
                        $playerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $matches = $query->latest()->paginate($perPage);

        return response()->json([
            'status'  => true,
            'message' => 'Match voting list retrieved successfully',
            'data'    => $matches->items(),
            'meta'    => [
                'current_page' => $matches->currentPage(),
                'last_page'    => $matches->lastPage(),
                'per_page'     => $matches->perPage(),
                'total'        => $matches->total(),
                'prev'         => $matches->currentPage() > 1,
                'next'         => $matches->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'game_id' => 'required|exists:games,id',
            'player_one_id' => 'required|exists:users,id|different:player_two_id',
            'player_two_id' => 'required|exists:users,id',

            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $match = MatchForVoting::create([
            'game_id' => $request->game_id,
            'player_one_id' => $request->player_one_id,
            'player_two_id' => $request->player_two_id,
            'total_vote' => 0,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Match voting created successfully',
            'data' => $match
        ]);
    }

    public function edit($id)
    {
        $match = MatchForVoting::with([
            'game:id,name',
            'playerOne:id,name',
            'playerTwo:id,name',
        ])
            ->find($id);

        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $match
        ]);
    }

    public function update(Request $request, $id)
    {
        $match = MatchForVoting::find($id);

        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $request->validate([
            'game_id' => 'sometimes|exists:games,id',
            'player_one_id' => 'sometimes|exists:users,id|different:player_two_id',
            'player_two_id' => 'sometimes|exists:users,id',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
        ]);

        $match->update($request->only([
            'game_id',
            'player_one_id',
            'player_two_id',
            'start_time',
            'end_time'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Match updated successfully',
            'data' => $match
        ]);
    }

    public function destroy($id)
    {
        $match = MatchForVoting::find($id);

        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $match->delete();

        return response()->json([
            'success' => true,
            'message' => 'Match deleted successfully'
        ]);
    }

    // public function vote(Request $request)
    // {
    //     $request->validate([
    //         'match_for_voting_id' => 'required|exists:match_for_votings,id',
    //         'total_vote' => 'required|integer|min:1',
    //     ]);

    //     $userId = auth('api')->id();

    //     DB::beginTransaction();

    //     try {
    //         $user_balance = UserBalance::where('user_id', $userId)
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$user_balance || $user_balance->total_balance < $request->total_vote) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Insufficient balance'
    //             ], 400);
    //         }

    //         $match = MatchForVoting::find($request->match_for_voting_id);

    //         if (!$match || now()->lt($match->start_time) || now()->gt($match->end_time)) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Voting is not active'
    //             ], 400);
    //         }

    //         MatchVoter::create([
    //             'user_id' => $userId,
    //             'match_for_voting_id' => $match->id,
    //             'total_vote' => $request->total_vote,
    //         ]);

    //         $match->increment('total_vote', $request->total_vote);

    //         $user_balance->decrement('total_balance', $request->total_vote);

    //         $adminBalance = UserBalance::where('user_id', 1)
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$adminBalance) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Admin balance not found'
    //             ], 500);
    //         }

    //         $adminBalance->increment('total_balance', $request->total_vote);

    //         CoinTransaction::create([
    //             'user_id' => $userId,
    //             'type' => 'vote',
    //             'amount' => -$request->total_vote,
    //             'balance_after' => $user_balance->fresh()->total_balance,
    //             'reference' => 'Vote for match ID: ' . $match->id,
    //         ]);

    //         CoinTransaction::create([
    //             'user_id' => 1,
    //             'type' => 'vote',
    //             'amount' => $request->total_vote,
    //             'balance_after' => $adminBalance->fresh()->total_balance,
    //             'reference' => 'Received vote from user ID: ' . $userId,
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Vote submitted successfully',
    //             'data' => [
    //                 'match_id' => $match->id,
    //                 'total_vote' => $match->total_vote
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function vote(Request $request)
    {
        $request->validate([
            'match_for_voting_id' => 'required|exists:match_for_votings,id',
        ]);

        $userId = auth('api')->id();

        $alreadyVoted = MatchVoter::where('user_id', $userId)
            ->where('match_for_voting_id', $request->match_for_voting_id)
            ->exists();

        if ($alreadyVoted) {
            return response()->json([
                'status' => false,
                'message' => 'You have already voted for this match'
            ], 400);
        }

        DB::beginTransaction();

        try {
            MatchVoter::create([
                'user_id' => $userId,
                'match_for_voting_id' => $request->match_for_voting_id,
            ]);

            $match = MatchForVoting::find($request->match_for_voting_id);
            $match->increment('total_vote');

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Vote submitted successfully',
                'data' => [
                    'match_id' => $match->id,
                    'total_vote' => $match->total_vote
                ]
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function todaysMatches()
    {
        $matches = MatchForVoting::with([
            'game:id,name,image',
            'playerOne:id,name,image',
            'playerTwo:id,name,image',
        ])->get();

        return response()->json([
            'status' => true,
            'message' => 'Matches retrieved successfully',
            'data' => $matches
        ]);
    }
}
