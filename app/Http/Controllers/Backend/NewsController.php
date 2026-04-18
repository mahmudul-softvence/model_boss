<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsRequest;
use App\Http\Requests\UpdateNewsRequest;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    /**
     * Display a listing of the news.
     */
    public function index()
    {
        $news = News::latest()->paginate();

        return $this->sendResponse(NewsResource::collection($news));
    }

    /**
     * Store a newly created news in storage.
     */
    public function store(StoreNewsRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')
                ->store('news/images', 'public');
        }

        $news = News::create($data);

        return $this->sendResponse(NewsResource::make($news), 'News created successfully.', 201);
    }

    /**
     * Display the specified news.
     */
    public function show(News $news)
    {
        return $this->sendResponse(NewsResource::make($news));
    }

    /**
     * Update the specified news in storage.
     */
    public function update(UpdateNewsRequest $request, News $news)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {

            if ($news->image && Storage::disk('public')->exists($news->image)) {
                Storage::disk('public')->delete($news->image);
            }

            $data['image'] = $request->file('image')
                ->store('news/images', 'public');
        }

        $news->update($data);

        return $this->sendResponse($news, 'News updated successfully');
    }

    /**
     * Remove the specified news from storage.
     */
    public function destroy(News $news)
    {
        if ($news->image && Storage::disk('public')->exists($news->image)) {
            Storage::disk('public')->delete($news->image);
        }

        $news->delete();

        return $this->sendResponse([], 'News deleted successfully');
    }
}
