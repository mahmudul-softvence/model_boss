<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MatchController extends Controller
{
    public function index()
    {
        $matches = GameMatch::with([
            'game:id,name',
            'playerOne:id,name',
            'playerTwo:id,name',
            'winner:id,name'
        ])->latest()->get();

        return response()->json([
            'status'  => true,
            'message' => 'Matches retrieved successfully',
            'data'    => $matches,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'match_no'       => 'required|string|max:50|unique:game_matches,match_no',
            'game_id'        => 'required|exists:games,id',
            'player_one_id'  => 'required|exists:users,id',
            'player_one_bet' => 'required|numeric|min:0',
            'player_two_id'  => 'required|exists:users,id|different:player_one_id',
            'player_two_bet' => 'required|numeric|min:0',
            'type'           => 'required|string|max:50',
            'winner_id'      => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Optional: Validate winner belongs to match
        if ($request->winner_id &&
            ! in_array($request->winner_id, [$request->player_one_id, $request->player_two_id])) {
            return response()->json([
                'status'  => false,
                'message' => 'Winner must be one of the match players.',
            ], 422);
        }

        $match = GameMatch::create($request->all());

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
            'winner:id,name'
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
            'match_no'       => 'required|string|max:50|unique:game_matches,match_no,' . $match->id,
            'game_id'        => 'required|exists:games,id',
            'player_one_id'  => 'required|exists:users,id',
            'player_one_bet' => 'required|numeric|min:0',
            'player_two_id'  => 'required|exists:users,id|different:player_one_id',
            'player_two_bet' => 'required|numeric|min:0',
            'type'           => 'required|string|max:50',
            'winner_id'      => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->winner_id &&
            ! in_array($request->winner_id, [$request->player_one_id, $request->player_two_id])) {
            return response()->json([
                'status'  => false,
                'message' => 'Winner must be one of the match players.',
            ], 422);
        }

        $match->update($request->all());

        return response()->json([
            'status'  => true,
            'message' => 'Match updated successfully',
            'data'    => $match->load([
                'game:id,name',
                'playerOne:id,name',
                'playerTwo:id,name',
                'winner:id,name'
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
}
