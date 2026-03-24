<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MatchForVoting;
use Illuminate\Http\Request;

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
        ]);

        $match = MatchForVoting::create([
            'game_id' => $request->game_id,
            'player_one_id' => $request->player_one_id,
            'player_two_id' => $request->player_two_id,
            'total_vote' => 0
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
            'total_vote' => 'sometimes|integer|min:0'
        ]);

        $match->update($request->only([
            'game_id',
            'player_one_id',
            'player_two_id',
            'total_vote'
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
}
