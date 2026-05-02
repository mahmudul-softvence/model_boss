<?php

namespace App\Http\Controllers\Backend;

use App\Events\MatchCreated;
use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\PlayerVote;
use App\Models\Support;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $query = GameMatch::with([
            'game:id,name',
            'playerOne:id,artist_name,image,first_name',
            'playerTwo:id,artist_name,image,first_name',
            'winner:id,artist_name,image,first_name',
        ]);

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->game_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
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
                $q->where('match_no', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('game', function ($gameQuery) use ($search) {
                        $gameQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('playerOne', function ($playerQuery) use ($search) {
                        $playerQuery->where('artist_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('playerTwo', function ($playerQuery) use ($search) {
                        $playerQuery->where('artist_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%");
                    });
            });
        }

        $matches = $query
            ->orderBy('pin_to_top', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $data = $matches->getCollection()->map(function ($match) {

            $match->player_one = $match->playerOne ? [
                'id' => $match->playerOne->id,
                'name' => $match->playerOne->artist_name ?: $match->playerOne->first_name,
                'image' => $match->playerOne->image,
            ] : null;

            $match->player_two = $match->playerTwo ? [
                'id' => $match->playerTwo->id,
                'name' => $match->playerTwo->artist_name ?: $match->playerTwo->first_name,
                'image' => $match->playerTwo->image,
            ] : null;

            $match->winner = $match->winner ? [
                'id' => $match->winner->id,
                'name' => $match->winner->artist_name ?: $match->winner->first_name,
                'image' => $match->winner->image,
            ] : null;

            unset($match->playerOne, $match->playerTwo);

            return $match;
        });

        return response()->json([
            'status' => true,
            'message' => 'Matches retrieved successfully',
            'data' => $data,
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
        $validator = Validator::make($request->all(), [
            'game_id' => 'required|exists:games,id',
            'player_one_id' => 'required|exists:users,id',
            'player_one_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'player_two_id' => 'required|exists:users,id|different:player_one_id',
            'player_two_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'players_bet_amount' => 'required|numeric|min:0',
            'type' => 'required|string|max:50|in:upcoming',
            'match_date' => 'required|date|after_or_equal:today',
            'match_time' => 'required|date_format:H:i',
            'winner_percentage' => 'nullable|in:0,1',
            'loser_percentage' => 'nullable|in:0,1',
            'tiktok_link' => 'nullable|url',
            'twitch_link' => 'nullable|url',
            'rules' => 'nullable|string',
            'voting_time' => 'nullable|date|after_or_equal:now',
            'pin_to_top' =>  'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();

        if ($request->hasFile('player_one_logo')) {
            $data['player_one_logo'] = $request->file('player_one_logo')->store('logos', 'public');
        }

        if ($request->hasFile('player_two_logo')) {
            $data['player_two_logo'] = $request->file('player_two_logo')->store('logos', 'public');
        }

        do {
            $matchNo = random_int(100000, 999999);
        } while (GameMatch::where('match_no', $matchNo)->exists());

        $data['match_no'] = $matchNo;
        $data['player_one_bet'] = $data['players_bet_amount'];
        $data['player_two_bet'] = $data['players_bet_amount'];
        $data['player_one_total'] = $data['players_bet_amount'];
        $data['player_two_total'] = $data['players_bet_amount'];

        if ($request->filled('voting_time')) {
            $data['voting_time'] = Carbon::parse($request->voting_time);
        }

        $match = GameMatch::create($data);

        $match->load([
            'game:id,name',
            'playerOne:id,name',
            'playerTwo:id,name',
        ]);

        $users = User::role(['user', 'artist'])->pluck('id')->toArray();

        $players = [
            $data['player_one_id'],
            $data['player_two_id'],
        ];

        broadcast(new MatchCreated(
            $users,
            $players,
            $data['rules'] ?? null
        ))->toOthers();

        return response()->json([
            'status' => true,
            'message' => 'Match created successfully',
            'data' => $match->load([
                'game:id,name',
                'playerOne:id,name',
                'playerTwo:id,name',
            ]),
        ], 201);
    }

    public function edit($id)
    {
        $match = GameMatch::with([
            'game:id,name',
            'playerOne:id,artist_name,first_name',
            'playerTwo:id,artist_name,first_name',
        ])->find($id);

        if (! $match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found',
            ], 404);
        }

        $match->player_one = $match->playerOne ? [
            'id' => $match->playerOne->id,
            'name' => $match->playerOne->artist_name ?: $match->playerOne->first_name,
        ] : null;

        $match->player_two = $match->playerTwo ? [
            'id' => $match->playerTwo->id,
            'name' => $match->playerTwo->artist_name ?: $match->playerTwo->first_name,
        ] : null;

        unset($match->playerOne, $match->playerTwo);

        return response()->json([
            'status' => true,
            'message' => 'Match retrieved successfully',
            'data' => $match,
        ]);
    }

    public function update(Request $request, $id)
    {
        $match = GameMatch::find($id);

        if (! $match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'game_id' => 'required|exists:games,id',
            'player_one_id' => 'required|exists:users,id',
            'player_one_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'player_two_id' => 'required|exists:users,id|different:player_one_id',
            'player_two_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'players_bet_amount' => 'required|numeric|min:0',
            'type' => 'required|string|max:50',
            'match_date' => 'required|date',
            'match_time' => 'required|date_format:H:i',
            'winner_percentage' => 'nullable|in:0,1',
            'loser_percentage' => 'nullable|in:0,1',
            'tiktok_link' => 'nullable|url',
            'twitch_link' => 'nullable|url',
            'rules' => 'nullable|string',
            'voting_time' => 'nullable|date|after_or_equal:now',
            'pin_to_top' =>  'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();

        if ($request->hasFile('player_one_logo')) {

            $oldPath = $match->getRawOriginal('player_one_logo');

            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $data['player_one_logo'] = $request->file('player_one_logo')
                ->store('logos', 'public');
        }

        if ($request->hasFile('player_two_logo')) {

            $oldPath = $match->getRawOriginal('player_two_logo');

            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $data['player_two_logo'] = $request->file('player_two_logo')
                ->store('logos', 'public');
        }

        $data['match_no'] = $match->match_no;

        if ($data['players_bet_amount'] != $match->player_one_bet) {

            if ($match->player_one_total == $match->player_one_bet) {
                $data['player_one_bet'] = $data['players_bet_amount'];
                $data['player_one_total'] = $data['players_bet_amount'];
            } else {
                $data['player_one_bet'] = $data['players_bet_amount'];
                $data['player_one_total'] =
                    ($match->player_one_total - $match->player_one_bet)
                    + $data['players_bet_amount'];
            }
            if ($match->player_two_total == $match->player_two_bet) {
                $data['player_two_bet'] = $data['players_bet_amount'];
                $data['player_two_total'] = $data['players_bet_amount'];
            } else {
                $data['player_two_bet'] = $data['players_bet_amount'];
                $data['player_two_total'] =
                    ($match->player_two_total - $match->player_two_bet)
                    + $data['players_bet_amount'];
            }
        }

        if ($request->has('voting_time')) {
            $data['voting_time'] = $request->voting_time
                ? Carbon::parse($request->voting_time)
                : null;
        }

        $match->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Match updated successfully',
            'data' => $match->load([
                'game:id,name',
                'playerOne:id,name',
                'playerTwo:id,name',
            ]),
        ]);
    }

    public function destroy($id)
    {
        $match = GameMatch::find($id);

        if (! $match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found',
            ], 404);
        }

        $playerOneLogo = $match->getRawOriginal('player_one_logo');

        if ($playerOneLogo && Storage::disk('public')->exists($playerOneLogo)) {
            Storage::disk('public')->delete($playerOneLogo);
        }

        $playerTwoLogo = $match->getRawOriginal('player_two_logo');

        if ($playerTwoLogo && Storage::disk('public')->exists($playerTwoLogo)) {
            Storage::disk('public')->delete($playerTwoLogo);
        }

        $match->delete();

        return response()->json([
            'status' => true,
            'message' => 'Match deleted successfully',
        ]);
    }

    public function players($id)
    {
        $match = GameMatch::with([
            'playerOne:id,artist_name,first_name',
            'playerTwo:id,artist_name,first_name',
        ])->find($id);

        if (! $match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Players retrieved successfully',
            'data' => [
                'player_one' => $match->playerOne ? [
                    'id' => $match->playerOne->id,
                    'name' => $match->playerOne->artist_name ?: $match->playerOne->first_name,
                ] : null,

                'player_two' => $match->playerTwo ? [
                    'id' => $match->playerTwo->id,
                    'name' => $match->playerTwo->artist_name ?: $match->playerTwo->first_name,
                ] : null,
            ],
        ]);
    }

    public function allPlayers(Request $request)
    {
        $query = User::role('artist')
            ->select('id', 'artist_name', 'first_name');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('artist_name', 'LIKE', "%{$search}%")
                ->orWhere('first_name', 'LIKE', "%{$search}%");
            });
        }

        $players = $query->get()->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->artist_name ?: $player->first_name,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'All players retrieved successfully',
            'data' => $players,
        ]);
    }

    // For landing page
    public function landing(Request $request)
    {
        $filter = $request->type ?? 'all';
        $perPage = $request->per_page ?? 10;

        $super = User::where('id', 1)->select('image')->first();

        $matches = GameMatch::with([
            'game:id,name,image',
            'playerOne:id,artist_name,first_name,image',
            'playerTwo:id,artist_name,first_name,image',
        ])
            ->when($filter === 'live', function ($query) {
                $query->where('confirmation_status', 1)
                    ->where('type', 'live');
            })
            ->when($filter === 'past', function ($query) {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('confirmation_status', 1)
                            ->where('type', '!=', 'live');
                    })
                    ->orWhere('confirmation_status', 2);
                });
            })
            ->when($filter === 'upcoming', function ($query) {
                $query->where('confirmation_status', 0);
            })
            ->orderBy('pin_to_top', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

            $data = $matches->getCollection()->map(function ($match) {

                $match->player_one = $match->playerOne ? [
                    'id' => $match->playerOne->id,
                    'name' => $match->playerOne->artist_name ?: $match->playerOne->first_name,
                    'image' => $match->playerOne->image,
                ] : null;

                $match->player_two = $match->playerTwo ? [
                    'id' => $match->playerTwo->id,
                    'name' => $match->playerTwo->artist_name ?: $match->playerTwo->first_name,
                    'image' => $match->playerTwo->image,
                ] : null;

                unset($match->playerOne, $match->playerTwo);

                return $match;
            });

        return response()->json([
            'status' => true,
            'message' => 'Matches retrieved successfully',
            'data' => $data,
            'model_picture' => $super->image ? asset('storage/' . $super->image) : null,
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
    
    public function socketMatch($id)
    {
        $super = User::where('id', 1)->select('image')->first();

        $match = GameMatch::with([
            'game:id,name,image',
            'playerOne:id,artist_name,first_name,image',
            'playerTwo:id,artist_name,first_name,image',
        ])->find($id);

        if (! $match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found',
            ], 404);
        }

        $votes = PlayerVote::where('match_id', $match->id)
            ->select('voted_player_id', DB::raw('SUM(total_vote) as total'))
            ->groupBy('voted_player_id')
            ->pluck('total', 'voted_player_id');

        $playerOneVotes = $votes[$match->player_one_id] ?? 0;
        $playerTwoVotes = $votes[$match->player_two_id] ?? 0;

        $topVoters = [];

        if ($match->vote_start_time) {

            $topUsers = PlayerVote::selectRaw('user_id, SUM(total_vote) as total_votes')
                ->where('match_id', $match->id)
                ->groupBy('user_id')
                ->orderByDesc('total_votes')
                ->limit(10)
                ->get();

            $voteDetails = PlayerVote::with('user:id,artist_name,first_name,image')
                ->where('match_id', $match->id)
                ->whereIn('user_id', $topUsers->pluck('user_id'))
                ->get()
                ->groupBy('user_id');

            $topVoters = $topUsers->map(function ($row, $index) use ($voteDetails) {

                $userVotes = $voteDetails[$row->user_id] ?? collect();
                $first = $userVotes->first();

                $user = $first?->user;

                return [
                    'user_id' => $row->user_id,
                    'serial_no' => str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'total_votes' => (int) $row->total_votes,
                    'vote_breakdown' => $userVotes->pluck('total_vote')->implode(', '),
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->artist_name ?: $user->first_name,
                        'image' => $user->image ? asset('storage/'.$user->image) : null,
                    ] : null,
                ];
            });
        }

        $getTopSupporters = function ($matchId, $limit = 10) {
            return Support::with('supporter:id,artist_name,first_name,image')
                ->where('match_id', $matchId)
                ->select(
                    'user_id',
                    DB::raw('GROUP_CONCAT(coin_amount ORDER BY id ASC) as supported_amounts'),
                    DB::raw('SUM(coin_amount) as total_amount')
                )
                ->groupBy('user_id')
                ->orderByDesc('total_amount')
                ->limit($limit)
                ->get()
                ->map(function ($support, $index) {
                    $u = $support->supporter;

                    return [
                        'user_id' => $support->user_id,
                        'serial_no' => str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                        'supported_amounts' => $support->supported_amounts,
                        'supporter' => $u ? [
                            'id' => $u->id,
                            'name' => $u->artist_name ?: $u->first_name,
                            'image' => $u->image ? asset('storage/'.$u->image) : null,
                        ] : null,
                    ];
                });
        };

        $topSupporters = $getTopSupporters($id);

        $playerOneSupport = Support::with('supporter:id,artist_name,first_name,image')
            ->where('match_id', $id)
            ->where('supported_player_id', $match->player_one_id)
            ->select('user_id', DB::raw('SUM(coin_amount) as total_amount'))
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->first();

        $p1 = $playerOneSupport?->supporter;

        $playerOneTopSupporter = $p1 ? [
            'id' => $p1->id,
            'name' => $p1->artist_name ?: $p1->first_name,
            'image' => $p1->image ? asset('storage/'.$p1->image) : null,
        ] : null;

        $playerTwoSupport = Support::with('supporter:id,artist_name,first_name,image')
            ->where('match_id', $id)
            ->where('supported_player_id', $match->player_two_id)
            ->select('user_id', DB::raw('SUM(coin_amount) as total_amount'))
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->first();

        $p2 = $playerTwoSupport?->supporter;

        $playerTwoTopSupporter = $p2 ? [
            'id' => $p2->id,
            'name' => $p2->artist_name ?: $p2->first_name,
            'image' => $p2->image ? asset('storage/'.$p2->image) : null,
        ] : null;

        $playerOneTotalSupporter = Support::where('match_id', $id)
            ->where('supported_player_id', $match->player_one_id)
            ->count();

        $playerTwoTotalSupporter = Support::where('match_id', $id)
            ->where('supported_player_id', $match->player_two_id)
            ->count();

        $match->player_one = $match->playerOne ? [
            'id' => $match->playerOne->id,
            'name' => $match->playerOne->artist_name ?: $match->playerOne->first_name,
            'image' => $match->playerOne->image,
        ] : null;

        $match->player_two = $match->playerTwo ? [
            'id' => $match->playerTwo->id,
            'name' => $match->playerTwo->artist_name ?: $match->playerTwo->first_name,
            'image' => $match->playerTwo->image,
        ] : null;

        unset($match->playerOne, $match->playerTwo);

        return response()->json([
            'status' => true,
            'message' => 'Match retrieved successfully',
            'data' => $match,
            'model_picture' => $super->image ? asset('storage/'.$super->image) : null,
            'top_supporters' => $topSupporters,
            'top_voters' => $topVoters,
            'player_one_top_supporter' => $playerOneTopSupporter,
            'player_one_total_supporter' => $playerOneTotalSupporter,
            'player_two_top_supporter' => $playerTwoTopSupporter,
            'player_two_total_supporter' => $playerTwoTotalSupporter,
            'player_one_votes' => $playerOneVotes,
            'player_two_votes' => $playerTwoVotes,
        ]);
    }

    public function togglePin($id)
    {
        $match = GameMatch::findOrFail($id);

        if ($match->pin_to_top == 1) {
            $match->pin_to_top = 0;
            $message = 'Match unpinned successfully';
        } else {
            $match->pin_to_top = 1;
            $message = 'Match pinned successfully';
        }

        $match->save();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'id' => $match->id,
                'pin_to_top' => $match->pin_to_top
            ]
        ]);
    }

}
