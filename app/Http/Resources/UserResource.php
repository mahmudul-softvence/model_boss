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

        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'nationality' => $this->nationality,
            'image' => $this->image
                ? ($this->provider
                    ? $this->image
                    : asset('storage/' . $this->image))
                : null,
            'provider' => $this->provider,
            'verified_at' => !is_null($this->email_verified_at),

            'suspended_until' => $suspension?->suspended_until,
            'is_permanent_suspended' => $suspension?->is_permanent ?? false,
            'suspension_reason' => $suspension?->reason,
            'note' => $suspension?->note,
            'total_post' => $this->posts()->count(),
            'role'  => $this->getRoleNames()->first(),
            'referral_no' => $this->referral_no,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'created_at' => $this->created_at,
        ];
    }
}
