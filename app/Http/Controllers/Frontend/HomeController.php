<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\GameResource;
use App\Http\Resources\LiveStatusResource;
use App\Http\Resources\NewsResource;
use App\Http\Resources\UserResource;
use App\Models\CheckLiveStatus;
use App\Models\Gallery;
use App\Models\Game;
use App\Models\News;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

    public function get_all_games()
    {
        $games = Game::latest()->get();

        return $this->sendResponse(GameResource::collection($games));
    }

    public function get_users_for_select(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $users = User::latest()
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('artist_name', 'like', '%' . $search . '%')
                        ->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            })
            ->limit(10)
            ->get()
            ->map(fn(User $user): array => [
                'id' => $user->id,
                'text' => $this->selectUserLabel($user),
            ]);

        return $this->sendResponse($users);
    }

    public function get_live_staus()
    {
        $live_status = CheckLiveStatus::get();

        $data = [
            'live_status' => LiveStatusResource::collection($live_status),
        ];

        return $this->sendResponse($data);
    }

    public function search_artist(Request $request)
    {
        $search = $request->query('search');

        if (empty($search)) {
            return $this->sendResponse([]);
        }

        $artist = User::role(['user', 'artist'])
            ->withCount(['followers', 'following'])
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('artist_name', 'like', '%' . $search . '%');
            })
            ->get();

        $authFollowingIds = $request->user()
            ? $request->user()->following()->pluck('following_id')->all()
            : [];

        $data = $artist->map(function ($user) use ($authFollowingIds, $request) {
            $resource = UserResource::make($user)->toArray($request);
            $resource['is_following'] = in_array($user->id, $authFollowingIds, true);

            return $resource;
        });

        return $this->sendResponse($data);
    }

    private function selectUserLabel(User $user): string
    {
        if (! empty($user->artist_name)) {
            return $user->artist_name;
        }

        if ($user->show_name && ($user->full_name || $user->name)) {
            return $user->full_name ?? $user->name;
        }

        return 'User #' . $user->id;
    }
}
