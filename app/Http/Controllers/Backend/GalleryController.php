<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGalleryRequest;
use App\Http\Requests\UpdateGalleryRequest;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    /**
     * Display a listing of the gallery.
     *
     * @param null
     */
    public function index()
    {
        $galleries = Gallery::latest()->paginate(10);

        return $this->sendResponse(GalleryResource::collection($galleries));
    }

    /**
     * Store a newly created gallery in storage.
     *
     * @param  Request  $request
     */
    public function store(StoreGalleryRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('short_video')) {
            $validated['short_video'] = $request->file('short_video')
                ->store('gallery/videos', 'public');
        }

        if ($request->hasFile('short_video_thumb')) {
            $validated['short_video_thumb'] = $request->file('short_video_thumb')
                ->store('gallery/short_video_thumbs', 'public');
        }

        $gallery = Gallery::create($validated);

        return $this->sendResponse(GalleryResource::make($gallery), 'Gallery created successfully', 201);
    }

    /**
     * Display the specified gallery.
     */
    public function show(Gallery $gallery)
    {
        return $this->sendResponse(GalleryResource::make($gallery));
    }

    /**
     * Update the specified gallery in storage.
     *
     * @param  Request  $request
     */
    public function update(UpdateGalleryRequest $request, Gallery $gallery)
    {
        $validated = $request->validated();

        if ($request->hasFile('short_video')) {

            if ($gallery->short_video) {
                Storage::disk('public')->delete($gallery->short_video);
            }

            $validated['short_video'] = $request->file('short_video')
                ->store('gallery/videos', 'public');
        }

        if ($request->hasFile('short_video_thumb')) {

            if ($gallery->short_video_thumb) {
                Storage::disk('public')->delete($gallery->short_video_thumb);
            }

            $validated['short_video_thumb'] = $request->file('short_video_thumb')
                ->store('gallery/short_video_thumbs', 'public');
        }

        $gallery->update($validated);

        return $this->sendResponse(GalleryResource::make($gallery), 'Gallery updated successfully');
    }

    /**
     * Remove the specified gallery from storage.
     */
    public function destroy(Gallery $gallery)
    {
        if ($gallery->short_video) {
            Storage::disk('public')->delete($gallery->short_video);
        }

        if ($gallery->short_video_thumb) {
            Storage::disk('public')->delete($gallery->short_video_thumb);
        }

        $gallery->delete();

        return $this->sendResponse([], 'Gallery deleted successfully');
    }
}
