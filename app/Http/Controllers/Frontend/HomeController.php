<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\LiveStatusResource;
use App\Http\Resources\NewsResource;
use App\Http\Resources\UserResource;
use App\Models\CheckLiveStatus;
use App\Models\Gallery;
use App\Models\News;
use App\Models\User;
use Illuminate\Http\Request;

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


    public function get_live_staus()
    {
        $live_status = CheckLiveStatus::get();

        $data = [
            'live_status' => LiveStatusResource::collection($live_status)
        ];

        return $this->sendResponse($data);
    }


    public function search_artist(Request $request)
    {
        $search = $request->query('search');

        if (empty($search)) {
            return $this->sendResponse([]);
        }

        $artist = User::role('artist')
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            })
            ->get();

        return $this->sendResponse(UserResource::collection($artist));
    }
}
