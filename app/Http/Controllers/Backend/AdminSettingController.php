<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminSettingController extends Controller
{
    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|max:255|unique:users,email,' . $user->id,
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'phone'       => 'required|string|max:20',
            'nationality' => 'required|string|max:100',
        ]);

        if ($request->hasFile('image')) {

            if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }

            $validated['image'] = $request->file('image')->store('users', 'public');
        }

        $user->update($validated);

        return $this->sendResponse(UserResource::make($user), 'User updated successfully');
    }

    public function change_password(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:6|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return $this->sendResponse([], 'Invalid cradentials', 422);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return $this->sendResponse([], 'Password changed successfully');
    }


    public function auto_accept_withdraw(Request $request)
    {
        $request->validate([
            'value' => 'required|in:true,false',
        ]);

        Setting::updateOrCreate(
            ['key' => 'auto_accept_withdrawals'],
            ['value' => $request->value]
        );

        $data = [
            'key'   => 'auto_accept_withdrawals',
            'value' => $request->value
        ];

        return $this->sendResponse($data, 'Auto accept withdraw updated');
    }
}
