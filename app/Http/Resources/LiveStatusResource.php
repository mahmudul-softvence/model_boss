<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveStatusResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform_name' => $this->platform_name,
            'platform_live_status' => $this->platform_live_status,
            'mode' => $this->mode,
            'live_started_at' => $this->live_started_at,
            'live_stopped_at' => $this->live_stopped_at
        ];
    }
}
