<?php

namespace App\Http\Controllers\Backend;

use App\Events\MatchVoteUpdated;
use App\Events\VotingStarted;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\GameMatch;
use App\Models\MatchForVoting;
use App\Models\MatchVoter;
use App\Models\PlayerVote;
use App\Models\User;
use App\Models\UserBalance;
use Carbon\Carbon;
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
            'status' => true,
            'message' => 'Match voting list retrieved successfully',
            'data' => $matches->items(),
            'meta' => [
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
                'prev' => $matches->currentPage() > 1,
                'next' => $matches->hasMorePages(),
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
            'data' => $match,
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

        if (! $match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $match,
        ]);
    }

    public function update(Request $request, $id)
    {
        $match = MatchForVoting::find($id);

        if (! $match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found',
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
            'end_time',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Match updated successfully',
            'data' => $match,
        ]);
    }

    public function destroy($id)
    {
        $match = MatchForVoting::find($id);

        if (! $match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found',
            ], 404);
        }

        $match->delete();

        return response()->json([
            'success' => true,
            'message' => 'Match deleted successfully',
        ]);
    }

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
                'message' => 'You have already voted for this match',
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
                    'total_vote' => $match->total_vote,
                ],
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
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
            'data' => $matches,
        ]);
    }

    public function votePlayer(Request $request, $match_id)
    {
        $request->validate([
            'player_id' => 'required|integer',
            'total_vote' => 'required|integer|min:1',
        ]);

        $totalVote = $request->input('total_vote');
        $halfVote = $totalVote / 2;

        $match = GameMatch::with(['playerOne', 'playerTwo'])->find($match_id);

        if (! $match || ! in_array($request->player_id, [$match->player_one_id, $match->player_two_id])) {
            return response()->json([
                'status' => false,
                'message' => 'Player not found in this match',
            ], 404);
        }

        if (! $match->vote_start_time) {
            return response()->json([
                'status' => false,
                'message' => 'Voting has not started yet',
            ], 400);
        }

        $voteEndTime = Carbon::parse($match->voting_time);

        if (now()->greaterThan($voteEndTime)) {
            return response()->json([
                'status' => false,
                'message' => 'Voting time is over',
            ], 400);
        }

        $userId = auth('api')->id();

        DB::beginTransaction();

        try {
            $user_balance = UserBalance::where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $user_balance || $user_balance->total_balance < $halfVote) {
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient balance',
                ], 400);
            }

            PlayerVote::create([
                'user_id' => $userId,
                'voted_player_id' => $request->player_id,
                'match_id' => $match_id,
                'total_vote' => $totalVote,
            ]);

            $user_balance->decrement('total_balance', $halfVote);

            $adminBalance = UserBalance::where('user_id', 1)
                ->lockForUpdate()
                ->first();

            if (! $adminBalance) {
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Admin balance not found',
                ], 500);
            }

            $adminBalance->increment('total_balance', $halfVote);

            CoinTransaction::create([
                'user_id' => $userId,
                'type' => 'vote',
                'amount' => -$halfVote,
                'balance_after' => $user_balance->fresh()->total_balance,
                'reference' => 'Vote for match ID: '.$match->id,
            ]);

            CoinTransaction::create([
                'user_id' => 1,
                'type' => 'vote',
                'amount' => $halfVote,
                'balance_after' => $adminBalance->fresh()->total_balance,
                'reference' => 'Received vote from user ID: '.$userId,
            ]);

            DB::commit();

            $match = $match->fresh()->load(['playerOne', 'playerTwo']);


            $playerVotes = PlayerVote::selectRaw('voted_player_id, SUM(total_vote) as total')
                ->where('match_id', $match->id)
                ->groupBy('voted_player_id')
                ->pluck('total', 'voted_player_id');

            $playerOneVotes = $playerVotes[$match->player_one_id] ?? 0;
            $playerTwoVotes = $playerVotes[$match->player_two_id] ?? 0;

            $topUsers = PlayerVote::selectRaw('user_id, SUM(total_vote) as total_votes')
                ->where('match_id', $match->id)
                ->groupBy('user_id')
                ->orderByDesc('total_votes')
                ->limit(10)
                ->pluck('total_votes', 'user_id');

            $users = User::whereIn('id', $topUsers->keys())
                ->get()
                ->keyBy('id');

            $topVotersRaw = PlayerVote::selectRaw('user_id, SUM(total_vote) as total_votes')
                ->where('match_id', $match->id)
                ->groupBy('user_id')
                ->orderByDesc('total_votes')
                ->limit(10)
                ->get();

            $votes = PlayerVote::with('user:id,name,image')
                ->where('match_id', $match->id)
                ->whereIn('user_id', $topVotersRaw->pluck('user_id'))
                ->get()
                ->groupBy('user_id');
            $topVoters = $topVotersRaw->values()->map(function ($row, $index) use ($votes) {

                $userVotes = $votes[$row->user_id] ?? collect();
                $sortedVotes = $userVotes->sortByDesc('total_vote')->values();
                $first = $sortedVotes->first();

                return [
                    'user_id' => $row->user_id,
                    'serial_no' => str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'total_votes' => (int) $row->total_votes,

                    'vote_breakdown' => $sortedVotes
                        ->pluck('total_vote')
                        ->implode(', '),

                    'user' => [
                        'id' => $first?->user->id,
                        'name' => $first?->user->name,
                        'image' => optional($first?->user)->image_url,
                    ],
                ];
            });

            event(new MatchVoteUpdated([
                'match_id' => $match->id,
                'match_no' => $match->match_no,

                'player_one' => [
                    'id' => $match->playerOne->id,
                    'name' => $match->playerOne->name,
                    'image' => $match->playerOne->image_url,
                    'total_votes' => $playerOneVotes,
                ],

                'player_two' => [
                    'id' => $match->playerTwo->id,
                    'name' => $match->playerTwo->name,
                    'image' => $match->playerTwo->image_url,
                    'total_votes' => $playerTwoVotes,
                ],

                'top_voters' => $topVoters,

                'vote_start_time' => $match->vote_start_time,
                'voting_time' => $match->voting_time,
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Vote submitted successfully',
                'data' => [
                    'match_id' => $match->id,
                    'top_voters' => $topVoters,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function startVote($match_id)
    {

        $match = GameMatch::with(['playerOne', 'playerTwo'])->find($match_id);
        if (! $match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found',
            ], 404);
        }

        if ($match->vote_start_time !== null) {
            return response()->json([
                'status' => false,
                'message' => 'Voting already started for this match',
            ], 400);
        }

        if ($match->voting_time === null) {
            return response()->json([
                'status' => false,
                'message' => 'Voting time not set for this match',
            ], 400);
        }

        if ($match->voting_time <= now()) {
            return response()->json([
                'status' => false,
                'message' => 'Voting time already expired',
            ], 400);
        }

        $match->vote_start_time = now();
        $match->save();

        event(new VotingStarted($match));

        return response()->json([
            'status' => true,
            'message' => 'Voting started successfully',
        ]);
    }
}
