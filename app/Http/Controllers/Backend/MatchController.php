<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Traits\HasRoles;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $query = GameMatch::with([
            'game:id,name',
            'playerOne:id,name',
            'playerTwo:id,name',
            'winner:id,name'
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
            'message' => 'Matches retrieved successfully',
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
        $validator = Validator::make($request->all(), [
            // 'match_no'        => 'required|string|max:50|unique:game_matches,match_no',
            'game_id'            => 'required|exists:games,id',
            'player_one_id'      => 'required|exists:users,id',
            'player_two_id'      => 'required|exists:users,id|different:player_one_id',
            'players_bet_amount' => 'required|numeric|min:0',
            'type'               => 'required|string|max:50',
            'match_date'         => 'required|date|after_or_equal:today',
            'match_time'         => 'required|date_format:H:i',
            'winner_percentage'  => 'nullable|in:0,1',
            'loser_percentage'   => 'nullable|in:0,1',
            'tiktok_link'        => 'nullable|url',
            'twitch_link'        => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $request->all();

        do {
            $matchNo = random_int(100000, 999999);
        } while (GameMatch::where('match_no', $matchNo)->exists());

        $data['match_no'] = $matchNo;
        $data['player_one_bet'] = $data['players_bet_amount'];
        $data['player_two_bet'] = $data['players_bet_amount'];
        $data['player_one_total'] = $data['players_bet_amount'];
        $data['player_two_total'] = $data['players_bet_amount'];

        $match = GameMatch::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Match created successfully',
            'data'    => $match->load([
                'game:id,name',
                'playerOne:id,name',
                'playerTwo:id,name'
            ]),
        ], 201);
    }

    public function edit($id)
    {
        $match = GameMatch::with([
            'game:id,name',
            'playerOne:id,name',
            'playerTwo:id,name',
        ])->find($id);

        if (! $match) {
            return response()->json([
                'status'  => false,
                'message' => 'Match not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Match retrieved successfully',
            'data'    => $match,
        ]);
    }

    public function update(Request $request, $id)
    {
        $match = GameMatch::find($id);

        if (! $match) {
            return response()->json([
                'status'  => false,
                'message' => 'Match not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            // 'match_no'          => 'required|string|max:50|unique:game_matches,match_no,' . $match->id,
            'game_id'              => 'required|exists:games,id',
            'player_one_id'        => 'required|exists:users,id',
            'player_two_id'        => 'required|exists:users,id|different:player_one_id',
            'players_bet_amount'   => 'required|numeric|min:0',
            'type'                 => 'required|string|max:50',
            'match_date'           => 'required|date',
            'match_time'           => 'required|date_format:H:i',
            'winner_percentage'    => 'nullable|in:0,1',
            'loser_percentage'     => 'nullable|in:0,1',
            'tiktok_link'          => 'nullable|url',
            'twitch_link'          => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $request->all();
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

        $match->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Match updated successfully',
            'data'    => $match->load([
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
                'status'  => false,
                'message' => 'Match not found',
            ], 404);
        }

        $match->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Match deleted successfully',
        ]);
    }

    public function players($id)
    {
        $match = GameMatch::with([
            'playerOne:id,name',
            'playerTwo:id,name',
        ])->find($id);

        if (! $match) {
            return response()->json([
                'status'  => false,
                'message' => 'Match not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Players retrieved successfully',
            'data'    => [
                'player_one' => $match->playerOne,
                'player_two' => $match->playerTwo,
            ],
        ]);
    }

    public function allPlayers(Request $request)
    {
        $query = User::role('artist')
            ->select('id', 'name');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where('name', 'LIKE', "%{$search}%");
        }

        $players = $query->get();

        return response()->json([
            'status'  => true,
            'message' => 'All players retrieved successfully',
            'data'    => $players,
        ]);
    }

    // For landing page
    public function landing(Request $request)
    {
        $filter  = $request->type ?? 'all';
        $perPage = $request->per_page ?? 10;

        $matches = GameMatch::with([
            'game:id,name,image',
            'playerOne:id,name,image',
            'playerTwo:id,name,image',
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
        ->latest()
        ->paginate($perPage);

        return response()->json([
            'status'  => true,
            'message' => 'Matches retrieved successfully',
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


}
