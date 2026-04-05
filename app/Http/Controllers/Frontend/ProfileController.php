<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();

        if ($request->hasFile('image')) {

            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }

            $imagePath = $request->file('image')
                ->store('users/images', 'public');

            $user->image = $imagePath;
        }

        $user->fill([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'address' => $validated['address'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'state' => $validated['state'] ?? null,
        ]);

        $user->save();

        return $this->sendResponse(UserResource::make($user));
    }


    public function show_artist_prifile($id)
    {
        $user = User::find($id);

        $userBalance = UserBalance::where('user_id', $user->id)->first();

        $isFollowed = false;

        if (auth()->check()) {
            $isFollowed = auth()->user()->following()->where('following_id', $id)->exists();
        }


        $data = [
            'user' => UserResource::make($user),
            'is_followed' => $isFollowed,

            'total_earning' => $userBalance->total_earning ?? 0,
            'total_referral_earning' => $userBalance->total_referral_earning ?? 0,
            'total_tip_received' => $userBalance->total_tip_received ?? 0,
            'total_withdraw' => $userBalance->total_withdraw ?? 0,
            'total_balance' => $userBalance->total_balance ?? 0,
            'total_bet' => $userBalance->total_bet ?? 0,
        ];

        return $this->sendResponse($data);
    }

    public function show_artist_posts($id)
    {
        $user = User::find($id);

        $posts = $user->posts()->latest()->get();

        return $this->sendResponse([
            'posts' => PostResource::collection($posts),
        ]);
    }
}
