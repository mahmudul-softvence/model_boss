<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\NewsResource;
use App\Models\Gallery;
use App\Models\News;
use Illuminate\Support\Facades\Request;

class HomeController extends Controller
{
    public function get_featured_news(Request $request)
    {
        $per_page = $request->query('per_page', 5);
        $featured_posts = News::latest()->paginate($per_page);
        return $this->sendResponse(NewsResource::collection($featured_posts));
    }


    public function get_featured_gallery(Request $request)
    {
        $per_page = $request->query('per_page', 5);
        $featured_gallery = Gallery::latest()->paginate($per_page);
        return $this->sendResponse(GalleryResource::collection($featured_gallery));
    }
}
