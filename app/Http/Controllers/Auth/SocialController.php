<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'facebook', 'apple'];

    /**
     * Get the social authentication redirect URL.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirect(string $provider)
    {
        if (! $this->supportsProvider($provider)) {
            return $this->sendError('Unsupported provider', [], 400);
        }

        $socialite = Socialite::driver($provider)->stateless();

        if ($provider === 'google') {
            $socialite->with([
                'prompt' => 'select_account',
            ]);
        }

        if ($provider === 'apple') {
            $socialite->scopes(['name', 'email']);
        }

        return $this->sendResponse([
            'url' => $socialite->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle the social authentication callback.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback(string $provider)
    {
        if (! $this->supportsProvider($provider)) {
            return $this->sendError('Unsupported provider', [], 400);
        }

        $socialUser = Socialite::driver($provider)->stateless()->user();

        $providerId = $socialUser->getId();
        $email = $socialUser->getEmail();

        $user = User::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $avatarPath = null;
                if ($socialUser->getAvatar()) {
                    $avatarContents = file_get_contents($socialUser->getAvatar());
                    $avatarName = 'users/images/'.Str::random(40).'.jpg';
                    Storage::disk('public')->put($avatarName, $avatarContents);
                    $avatarPath = $avatarName;
                }

                $user->update([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'image' => $avatarPath ?? $user->image,
                ]);
            }
        }

        if (! $user) {
            if (! $email) {
                $email = $providerId.'@'.$provider.'.local';
            }

            $name = $socialUser->getName()
                ?? $socialUser->getNickname()
                ?? Str::before($email, '@')
                ?? 'User';

            $nameParts = User::splitFullName($name);

            $avatarPath = null;
            if ($socialUser->getAvatar()) {
                $avatarContents = file_get_contents($socialUser->getAvatar());
                $avatarName = 'users/images/'.Str::random(40).'.jpg';
                Storage::disk('public')->put($avatarName, $avatarContents);
                $avatarPath = $avatarName;
            }

            $user = User::create([
                'first_name' => $nameParts['first_name'] ?? 'User',
                'middle_name' => $nameParts['middle_name'],
                'last_name' => $nameParts['last_name'],
                'email' => $email,
                'image' => $avatarPath,
                'provider' => $provider,
                'provider_id' => $providerId,
            ]);

            $user->markEmailAsVerified();
            $user->assignRole(UserRole::USER);
        }

        $user->load('suspension');

        if ($user->isSuspended()) {

            $suspension = $user->suspension;

            $data = [
                'success' => false,
                'suspended' => true,
                'permanent' => $suspension?->is_permanent ?? false,
                'until' => $suspension?->suspended_until,
                'reason' => $suspension?->reason,
                'note' => $suspension?->note,
            ];

            $encodedData = base64_encode(json_encode($data));

            return redirect()->away(
                config('app.frontend_url').'/'.$provider.'/callback?data='.$encodedData
            );
        }

        $token = auth()->login($user);

        $data = $this->respondWithToken($token);

        $encodedData = base64_encode(json_encode($data));

        return redirect()->away(
            config('app.frontend_url').'/'.$provider.'/callback?data='.$encodedData
        );
    }

    /**
     * Get the token array structure.
     *
     * @param  string  $token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $user = auth()->user();

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => UserResource::make($user)->resolve(),

        ];
    }

    private function supportsProvider(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED_PROVIDERS, true);
    }
}
