<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the post.
     */
    public function index()
    {
        $posts = auth()->user()->posts()->get();

        return $this->sendResponse($posts);
    }

    /**
     * Store a newly created post in storage.
     */
    public function store(StorePostRequest $request)
    {
        $validated = $request->validated();

        $imagePath = $request->file('image')->store('posts');

        $post = auth()->user()->posts()->create([
            'image' => $imagePath,
            'description' => $validated['description'] ?? null,
        ]);

        return $this->sendResponse(PostResource::make($post), 'Post created successfully', 201);
    }

    /**
     * Display the specified post.
     */
    public function show(Post $post)
    {
        return $this->sendResponse(PostResource::make($post), 'Post retrieved successfully.');
    }

    /**
     * Update the specified post in storage.
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        abort_unless($post->user_id === auth()->id(), 403, 'This action is unauthorized.');

        $validated = $request->validated();

        if ($request->hasFile('image')) {

            if ($post->image && Storage::disk()->exists($post->image)) {
                Storage::disk()->delete($post->image);
            }

            $validated['image'] = $request->file('image')->store('posts');
        }

        $post->update($validated);

        return $this->sendResponse(PostResource::make($post), 'Post updated successfully.');
    }

    /**
     * Remove the specified post from storage.
     */
    public function destroy(Post $post)
    {
        abort_unless($post->user_id === auth()->id(), 403, 'This action is unauthorized.');

        if ($post->image && Storage::disk()->exists($post->image)) {
            Storage::disk()->delete($post->image);
        }

        $post->delete();

        return $this->sendResponse([], 'Post deleted successfully.');
    }
}
