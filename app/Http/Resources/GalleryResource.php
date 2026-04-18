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
            'short_video' => $this->video_url,
            'short_video_thumb' => $this->image_url,
            'description' => $this->description,
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
