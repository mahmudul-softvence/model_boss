<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SocialLinks;
use Illuminate\Database\Seeder;

class SocialLinkSeeder extends Seeder
{
    /**
     * Seed the fixed social link placeholders without overwriting existing URLs.
     */
    public function run(): void
    {
        if (Setting::where('key', Setting::SOCIAL_LINKS_KEY)->exists()) {
            return;
        }

        Setting::create([
            'key' => Setting::SOCIAL_LINKS_KEY,
            'value' => json_encode(SocialLinks::defaults()),
        ]);
    }
}
