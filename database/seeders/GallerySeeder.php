<?php

namespace Database\Seeders;

use App\Models\Gallery;
use Illuminate\Database\Seeder;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        if (Gallery::query()->exists()) {
            return;
        }

        $items = [
            [
                'short_video' => 'galleries/placeholders/highlight-1.mp4',
                'short_video_thumb' => null,
                'description' => 'Opening night highlights — replace this placeholder video from the admin gallery.',
                'is_featured' => true,
            ],
            [
                'short_video' => 'galleries/placeholders/highlight-2.mp4',
                'short_video_thumb' => null,
                'description' => 'Clutch challenge moment — upload your own short clip to feature this slot.',
                'is_featured' => true,
            ],
            [
                'short_video' => 'galleries/placeholders/highlight-3.mp4',
                'short_video_thumb' => null,
                'description' => 'Crowd favorite finish — placeholder entry for the gallery grid.',
                'is_featured' => false,
            ],
        ];

        foreach ($items as $item) {
            Gallery::create($item);
        }
    }
}
