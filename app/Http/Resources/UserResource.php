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
            'name'  => $this->full_name ?? $this->name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'nationality' => $this->nationality,
            'address' => $this->address,
            'zip_code' => $this->zip_code,
            'state' => $this->state,
            'image' => $this->image_url,
            'provider' => $this->provider,
            'verified_at' => !is_null($this->email_verified_at),

            'suspended_until' => $suspension?->suspended_until,
            'is_permanent_suspended' => $suspension?->is_permanent ?? false,
            'suspension_reason' => $suspension?->reason,
            'note' => $suspension?->note,
            'total_post' => $this->posts()->count(),
            'role'  => $this->getRoleNames()->first(),
            'referral_no' => $this->referral_no,
            'game' => $this->game ? GameResource::make($this->game) : null,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
