<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\NewsResource;
use App\Models\Gallery;
use App\Models\News;

class HomeController extends Controller
{
    public function get_featured_news()
    {
        $featured_posts = News::published()->featured()->latest()->get();
        return $this->sendResponse(NewsResource::collection($featured_posts));
    }


    public function get_featured_gallery()
    {
        $featured_gallery = Gallery::featured()->latest()->get();
        return $this->sendResponse(GalleryResource::collection($featured_gallery));
    }
}
