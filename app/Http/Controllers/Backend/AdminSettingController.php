<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChallengeRuleRequest;
use App\Http\Requests\UpdateSocialLinkRequest;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminSettingController extends Controller
{
    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'phone' => 'nullable|string|max:20',
            'nationality' => 'nullable|string|max:100',
        ]);

        if ($request->hasFile('image')) {

            if ($user->image && Storage::disk()->exists($user->image)) {
                Storage::disk()->delete($user->image);
            }

            $validated['image'] = $request->file('image')->store('users');
        }

        $user->update($validated);

        return $this->sendResponse(UserResource::make($user), 'User updated successfully');
    }

    public function change_password(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->sendResponse([], 'Invalid cradentials', 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
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
            'key' => 'auto_accept_withdrawals',
            'value' => $request->value,
        ];

        return $this->sendResponse($data, 'Auto accept withdraw updated');
    }

    public function get_auto_accept_withdraw()
    {
        $data = [
            'key' => 'auto_accept_withdrawals',
            'value' => Setting::where('key', 'auto_accept_withdrawals')->first()?->value,
        ];

        return $this->sendResponse($data);
    }

    public function auto_offer_challenges(Request $request)
    {
        $request->validate([
            'value' => 'required|in:true,false',
        ]);

        Setting::updateOrCreate(
            ['key' => 'auto_offer_challenges'],
            ['value' => $request->value]
        );

        $data = [
            'key' => 'auto_offer_challenges',
            'value' => $request->value,
        ];

        return $this->sendResponse($data, 'Auto offer challenges updated');
    }

    public function get_auto_offer_challenges()
    {
        $data = [
            'key' => 'auto_offer_challenges',
            'value' => Setting::isEnabled('auto_offer_challenges') ? 'true' : 'false',
        ];

        return $this->sendResponse($data);
    }

    public function get_challenge_rules(): JsonResponse
    {
        return $this->sendResponse(['rules' => Setting::getChallengeRules()]);
    }

    public function update_challenge_rules(UpdateChallengeRuleRequest $request): JsonResponse
    {
        Setting::setChallengeRules($request->validated()['rules']);

        return $this->sendResponse(
            ['rules' => Setting::getChallengeRules()],
            'Challenge rules updated successfully',
        );
    }

    public function get_social_links(): JsonResponse
    {
        return $this->sendResponse(['links' => Setting::getSocialLinks()]);
    }

    public function update_social_links(UpdateSocialLinkRequest $request): JsonResponse
    {
        $links = Setting::setSocialLinks($request->validated());

        return $this->sendResponse(
            ['links' => $links],
            'Social links updated successfully',
        );
    }
}
