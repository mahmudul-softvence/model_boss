<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Follower;
use App\Models\User;
use App\Notifications\NewFollowerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowController extends Controller
{
    public function follow($id)
    {
        $authUser = auth()->user();
        $userToFollow = User::findOrFail($id);

        if ($userToFollow->id === $authUser->id) {
            return response()->json(['error' => 'You cannot follow yourself'], 400);
        }

        $isFirstTimeFollow = DB::transaction(function () use ($authUser, $userToFollow) {

            $follow = Follower::withTrashed()
                ->where('follower_id', $authUser->id)
                ->where('following_id', $userToFollow->id)
                ->first();

            // Already actively following: nothing to do.
            if ($follow && ! $follow->trashed()) {
                return false;
            }

            if ($follow) {
                // Previously unfollowed: restore the soft-deleted row, no notification.
                $follow->restore();
                $firstTime = false;
            } else {
                // Brand new follow: notify the followed user.
                Follower::create([
                    'follower_id' => $authUser->id,
                    'following_id' => $userToFollow->id,
                ]);
                $firstTime = true;
            }

            $authUser->increment('following_count');
            $userToFollow->increment('followers_count');

            return $firstTime;
        });

        if ($isFirstTimeFollow) {
            $userToFollow->notify(new NewFollowerNotification($authUser));
        }

        return $this->sendResponse(UserResource::make($userToFollow), 'Followed successfully');
    }

    public function unfollow($id)
    {
        $authUser = auth()->user();
        $userToUnfollow = User::findOrFail($id);

        DB::transaction(function () use ($authUser, $userToUnfollow) {

            $follow = Follower::where('follower_id', $authUser->id)
                ->where('following_id', $userToUnfollow->id)
                ->first();

            if ($follow) {

                $follow->delete();

                if ($authUser->following_count > 0) {
                    $authUser->decrement('following_count');
                }

                if ($userToUnfollow->followers_count > 0) {
                    $userToUnfollow->decrement('followers_count');
                }
            }
        });

        return $this->sendResponse([], 'Unfollowed successfully');
    }

    /**
     * Paginated followers of the given user, each flagged with `is_following`
     * to indicate whether the authenticated user already follows them.
     */
    public function userFollowers(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $limit = $request->query('limit', 20);

        $authFollowingIds = auth()->user()->following()->pluck('following_id')->all();

        $followers = $user->followers()->latest()->paginate($limit);

        $followers->getCollection()->transform(function ($follower) use ($authFollowingIds, $request) {
            $resource = UserResource::make($follower)->toArray($request);
            $resource['is_following'] = in_array($follower->id, $authFollowingIds, true);

            return $resource;
        });

        return $this->sendResponse([
            'followers' => $followers,
        ]);
    }

    /**
     * Paginated accounts the given user is following, each flagged with
     * `is_following` relative to the authenticated user.
     */
    public function userFollowing(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $limit = $request->query('limit', 20);

        $authFollowingIds = auth()->user()->following()->pluck('following_id')->all();

        $following = $user->following()->latest()->paginate($limit);

        $following->getCollection()->transform(function ($followee) use ($authFollowingIds, $request) {
            $resource = UserResource::make($followee)->toArray($request);
            $resource['is_following'] = in_array($followee->id, $authFollowingIds, true);

            return $resource;
        });

        return $this->sendResponse([
            'following' => $following,
        ]);
    }
}
