<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Follower;
use App\Models\User;
use App\Notifications\NewFollowerNotification;
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
}
