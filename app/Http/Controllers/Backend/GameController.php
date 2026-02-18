<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class GameController extends Controller
{
    public function index()
    {
        $games = Game::with('category:id,name')
            ->latest()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Games retrieved successfully',
            'data'    => $games,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:games,name',
            'category_id' => 'required|exists:categories,id',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $imagePath = null;

        if ($request->hasFile('image')) {
            $storedPath = $request->file('image')->store('games', 'public');
            $imagePath = 'storage/' . $storedPath;
        }

        $game = Game::create([
            'name'        => $request->name,
            'category_id' => $request->category_id,
            'image'       => $imagePath,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Game created successfully',
            'data'    => $game->load('category:id,name'),
        ], 201);
    }

    public function edit($id)
    {
        $game = Game::with('category:id,name')
            ->select('id', 'name', 'image', 'category_id')
            ->find($id);

        if (! $game) {
            return response()->json([
                'status'  => false,
                'message' => 'Game not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Game retrieved successfully',
            'data'    => $game,
        ]);
    }

    public function update(Request $request, $id)
    {
        $game = Game::find($id);

        if (! $game) {
            return response()->json([
                'status'  => false,
                'message' => 'Game not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:games,name,' . $game->id,
            'category_id' => 'required|exists:categories,id',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Update image
        if ($request->hasFile('image')) {

            if ($game->image && Storage::disk('public')->exists($game->image)) {
                Storage::disk('public')->delete($game->image);
            }

            $storedPath = $request->file('image')->store('games', 'public');
            $game->image = 'storage/' . $storedPath;
        }

        $game->name        = $request->name;
        $game->category_id = $request->category_id;
        $game->save();

        return response()->json([
            'status'  => true,
            'message' => 'Game updated successfully',
            'data'    => $game->load('category:id,name'),
        ]);
    }

    public function destroy($id)
    {
        $game = Game::find($id);

        if (! $game) {
            return response()->json([
                'status'  => false,
                'message' => 'Game not found',
            ], 404);
        }

        // Delete image
        if ($game->image && Storage::disk('public')->exists($game->image)) {
            Storage::disk('public')->delete($game->image);
        }

        $game->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Game deleted successfully',
        ]);
    }

    public function allGames()
    {
        $games = Game::select('id', 'name')->get();

        if ($games->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'No games found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'All games retrieved successfully',
            'data'    => $games,
        ]);
    }

    // For landing page
    public function landing()
    {
        $games = Game::select('id', 'name', 'image')->latest()->get();
        return response()->json([
            'status'  => true,
            'message' => 'Games retrieved successfully',
            'data'    => $games,
        ]);
    }

}
