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
    public function toArray($request)
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'image' => $this->provider ? $this->image : asset($this->image),
            'provider' => $this->provider,
            'verified_at' => !is_null($this->email_verified_at),
            'suspended_until'        => $this->suspended_until,
            'is_permanent_suspended' => $this->is_permanent_suspended,
            'suspension_reason' => $this->suspension_reason,
            'note'            => $this->suspension_note,
            'role'  => $this->getRoleNames()->first(),
            'referral_no' => $this->referral_no,
            'created_at' => $this->created_at
        ];
    }
}
