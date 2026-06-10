<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    private const PROFILE_VISIBILITY_FIELDS = [
        'show_email',
        'show_name',
        'show_total_earning',
        'show_total_referral_earning',
        'show_total_tip_received',
        'show_total_withdraw',
    ];

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
            'artist_name' => $validated['artist_name'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'],
            'zip_code' => $validated['zip_code'] ?? null,
            'state' => $validated['state'] ?? null,
            'social_verification_status' => $validated['social_verification_status'] ?? $user->social_verification_status,
            'social_verification_number' => $validated['social_verification_number'] ?? null,
        ]);

        $user->save();

        return $this->sendResponse(UserResource::make($user));
    }

    public function show_artist_prifile($id)
    {
        $user = User::with('userBalance')->findOrFail($id);

        $userBalance = $user->userBalance;

        $isFollowed = false;

        if (auth()->check()) {
            $isFollowed = auth()->user()->following()->where('following_id', $id)->exists();
        }

        $data = [
            'user' => UserResource::make($user),
            'is_followed' => $isFollowed,

            'total_earning' => $this->visibleBalanceAmount($user, $userBalance, 'total_earning', 'show_total_earning'),
            'total_referral_earning' => $this->visibleBalanceAmount($user, $userBalance, 'total_referral_earning', 'show_total_referral_earning'),
            'total_tip_received' => $this->visibleBalanceAmount($user, $userBalance, 'total_tip_received', 'show_total_tip_received'),
            'total_withdraw' => $this->visibleBalanceAmount($user, $userBalance, 'total_withdraw', 'show_total_withdraw'),
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

    public function see_follower()
    {
        $user = auth()->user();

        $followers = $user->followers()->latest()->get();

        return $this->sendResponse([
            'followers' => UserResource::collection($followers),
        ]);
    }

    public function see_following()
    {
        $user = auth()->user();

        $following = $user->following()->latest()->get();

        return $this->sendResponse([
            'following' => UserResource::collection($following),
        ]);
    }

    /**
     * Return paginated followers with an `is_following` flag so the front-end
     * can show a "Follow back" action.
     */
    public function followersList(Request $request)
    {
        $user = auth()->user();

        $limit = $request->query('limit', 20);

        $followingIds = $user->following()->pluck('following_id')->toArray();

        $followers = $user->followers()->latest()->paginate($limit);

        $followers->getCollection()->transform(function ($f) use ($followingIds, $request) {
            $resource = UserResource::make($f)->toArray($request);
            $resource['is_following'] = in_array($f->id, $followingIds, true);

            return $resource;
        });

        return $this->sendResponse([
            'followers' => $followers,
        ]);
    }

    /**
     * Return paginated following with an `is_followed_by` mutual flag.
     */
    public function followingList(Request $request)
    {
        $user = auth()->user();

        $limit = $request->query('limit', 20);

        $followerIds = $user->followers()->pluck('follower_id')->toArray();

        $following = $user->following()->latest()->paginate($limit);

        $following->getCollection()->transform(function ($f) use ($followerIds, $request) {
            $resource = UserResource::make($f)->toArray($request);
            $resource['is_followed_by'] = in_array($f->id, $followerIds, true);

            return $resource;
        });

        return $this->sendResponse([
            'following' => $following,
        ]);
    }

    public function followersCount()
    {
        $user = auth()->user();

        return $this->sendResponse([
            'followers_count' => $user->followers_count,
        ]);
    }

    public function followingCount()
    {
        $user = auth()->user();

        return $this->sendResponse([
            'following_count' => $user->following_count,
        ]);
    }

    public function changeFavGame(Request $request)
    {
        $validated = $request->validate([
            'game_id' => ['required', 'integer', 'exists:games,id'],
        ]);

        $user = auth()->user();
        $user->game_id = $validated['game_id'];
        $user->save();

        return $this->sendResponse(UserResource::make($user->fresh()));
    }

    public function toggleEmailVisibility(Request $request)
    {
        $validated = $request->validate([
            'show_email' => ['required', 'boolean'],
        ]);

        $user = auth()->user();
        $user->show_email = $validated['show_email'];
        $user->save();

        return $this->sendResponse(UserResource::make($user->fresh()));
    }

    public function updateVisibility(Request $request): JsonResponse
    {
        $validated = $request->validate($this->visibilityValidationRules());

        if ($validated === []) {
            return $this->sendError('At least one visibility field is required.', [], 422);
        }

        $user = auth()->user();
        $user->fill($validated);
        $user->save();

        return $this->sendResponse(UserResource::make($user->fresh()));
    }

    private function visibleBalanceAmount(User $user, ?UserBalance $userBalance, string $amountField, string $visibilityField): mixed
    {
        if ((bool) $user->{$visibilityField} || (auth()->check() && auth()->id() === $user->id)) {
            return $userBalance?->{$amountField} ?? 0;
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function visibilityValidationRules(): array
    {
        return collect(self::PROFILE_VISIBILITY_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => ['sometimes', 'boolean']])
            ->all();
    }
}
