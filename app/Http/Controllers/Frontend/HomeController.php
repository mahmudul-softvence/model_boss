<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\News;

class HomeController extends Controller
{
    public function get_featured_news()
    {
        $featured_posts = News::published()->featured()->latest()->get();
        return $this->sendResponse($featured_posts);
    }
}
