<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $categories = Category::latest()->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'prev' => $categories->currentPage() > 1,
                'next' => $categories->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:categories,name',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $imagePath = null;

        if ($request->hasFile('image')) {
            $storedPath = $request->file('image')->store('categories', 'public');
            $imagePath = 'storage/' . $storedPath;
        }

        $category = Category::create([
            'name'  => $request->name,
            'image' => $imagePath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    public function edit($id)
    {
        $category = Category::select('name', 'image')->find($id);

        if (! $category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Category retrieved successfully',
            'data' => $category,
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:categories,name,' . $category->id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            $oldImage = $category->getRawOriginal('image');

            if ($oldImage) {
                $oldImagePath = ltrim(str_replace('storage/', '', $oldImage), '/');

                if (Storage::disk('public')->exists($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            }

            $storedPath = $request->file('image')->store('categories', 'public');
            $category->image = 'storage/' . $storedPath;
        }

        $category->name = $request->name;
        $category->save();

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        if (Game::where('category_id', $category->id)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Category cannot be deleted because it is used by one or more games',
            ], 409);
        }

        $image = $category->getRawOriginal('image');
        if ($image) {
            $imagePath = ltrim(str_replace('storage/', '', $image), '/');

            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    // For landing page
    public function landing(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $categories = Category::select('id', 'name', 'image')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'prev' => $categories->currentPage() > 1,
                'next' => $categories->hasMorePages(),
            ],
        ]);
    }
}
