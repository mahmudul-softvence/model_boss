<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
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

        $user->name = $validated['name'];
        $user->phone_number = $validated['phone_number'] ?? null;
        $user->nationality = $validated['nationality'] ?? null;

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
}
