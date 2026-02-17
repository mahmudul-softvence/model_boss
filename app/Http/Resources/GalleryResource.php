<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'short_video' => $this->short_video
                ? asset('storage/' . $this->short_video)
                : null,

            'short_video_thumb' => $this->short_video_thumb
                ? asset('storage/' . $this->short_video_thumb)
                : null,

            'description' => $this->description,

            'created_at' => $this->created_at
        ];
    }
}
