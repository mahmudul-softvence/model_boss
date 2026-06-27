<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $suspension = $this->suspension;

        $isOwner = $request->user()?->is($this->resource) ?? false;
        $emailVisible = $this->show_email || $isOwner;
        $nameVisible = $this->show_name || $isOwner;

        $challengeWinsCount =
            (int) ($this->challenge_wins_count ??
                $this->challengeWins()->count());
        $challengeLossesCount =
            (int) ($this->challenge_losses_as_challenger_count ??
                $this->challengeLossesAsChallenger()->count()) +
            (int) ($this->challenge_losses_as_acceptor_count ??
                $this->challengeLossesAsAcceptor()->count());

        return [
            'id' => $this->id,
            'name' => $nameVisible ? $this->full_name ?? $this->name : null,
            'first_name' => $nameVisible ? $this->first_name : null,
            'middle_name' => $nameVisible ? $this->middle_name : null,
            'last_name' => $nameVisible ? $this->last_name : null,
            'artist_name' => $this->artist_name,
            'bio' => $this->bio,
            'email' => $emailVisible ? $this->email : null,
            'show_email' => (bool) $this->show_email,
            'show_name' => (bool) $this->show_name,
            'show_total_earning' => (bool) $this->show_total_earning,
            'show_total_referral_earning' => (bool) $this->show_total_referral_earning,
            'show_total_tip_received' => (bool) $this->show_total_tip_received,
            'show_total_withdraw' => (bool) $this->show_total_withdraw,
            'show_total_balance' => (bool) $this->show_total_balance,
            'show_total_bet' => (bool) $this->show_total_bet,
            'phone_number' => $this->phone_number,
            'nationality' => $this->nationality,
            'address' => $this->address,
            'city' => $this->city,
            'zip_code' => $this->zip_code,
            'state' => $this->state,
            'social_verification_status' => (bool) $this->social_verification_status,
            'social_verification_number' => $this->social_verification_status
                ? $this->social_verification_number
                : null,
            'is_player' => (bool) $this->is_player,
            'is_challenger' => (bool) $this->is_challenger,
            'challenge_wins_count' => $challengeWinsCount,
            'challenge_losses_count' => $challengeLossesCount,
            'challenge_total_count' => $challengeWinsCount + $challengeLossesCount,
            'image' => $this->image_url,
            'provider' => $this->provider,
            'verified_at' => ! is_null($this->email_verified_at),

            'suspended_until' => $suspension?->suspended_until,
            'is_permanent_suspended' => $suspension?->is_permanent ?? false,
            'suspension_reason' => $suspension?->reason,
            'note' => $suspension?->note,
            'total_post' => $this->posts()->count(),
            'role' => $this->getRoleNames()->first(),
            'referral_no' => $this->referral_no,
            'game' => $this->game ? GameResource::make($this->game) : null,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
