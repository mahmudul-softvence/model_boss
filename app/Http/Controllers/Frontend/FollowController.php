<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
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

        DB::transaction(function () use ($authUser, $userToFollow) {

            $alreadyFollowing = $authUser
                ->following()
                ->where('following_id', $userToFollow->id)
                ->exists();

            if (! $alreadyFollowing) {

                $authUser->following()->attach($userToFollow->id);

                $authUser->increment('following_count');
                $userToFollow->increment('followers_count');
            }
        });

        return $this->sendResponse(UserResource::make($userToFollow), 'Followed successfully');
    }


    public function unfollow($id)
    {
        $authUser = auth()->user();
        $userToUnfollow = User::findOrFail($id);

        DB::transaction(function () use ($authUser, $userToUnfollow) {

            $isFollowing = $authUser
                ->following()
                ->where('following_id', $userToUnfollow->id)
                ->exists();

            if ($isFollowing) {

                $authUser->following()->detach($userToUnfollow->id);

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
