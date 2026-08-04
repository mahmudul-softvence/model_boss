<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\Game;
use App\Models\News;
use App\Models\PromotionalTerm;
use App\Models\Setting;
use App\Support\SocialLinks;
use Database\Seeders\CategoryGameSeeder;
use Database\Seeders\GallerySeeder;
use Database\Seeders\NewsSeeder;
use Database\Seeders\PromotionalTermSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Tests\TestCase;

class ContentSeederTest extends TestCase
{
    public function test_content_seeders_populate_default_platform_data(): void
    {
        $this->seed([
            SettingSeeder::class,
            SocialLinkSeeder::class,
            PromotionalTermSeeder::class,
            CategoryGameSeeder::class,
            NewsSeeder::class,
            GallerySeeder::class,
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'auto_accept_withdrawals',
            'value' => 'false',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'auto_offer_challenges',
            'value' => 'false',
        ]);
        $this->assertNotEmpty(Setting::getChallengeRules());
        $this->assertSame(SocialLinks::defaults(), Setting::getSocialLinks());

        $promo = PromotionalTerm::currentContent();
        $this->assertSame(1000, $promo['prize']);
        $this->assertNotEmpty($promo['list']);

        $this->assertGreaterThanOrEqual(5, Category::count());
        $this->assertGreaterThanOrEqual(10, Game::count());
        $this->assertDatabaseHas('categories', ['name' => 'FPS']);
        $this->assertDatabaseHas('games', ['name' => 'Valorant']);

        $this->assertGreaterThanOrEqual(4, News::published()->count());
        $this->assertTrue(News::featured()->exists());

        $this->assertGreaterThanOrEqual(3, Gallery::count());
        $this->assertTrue(Gallery::featured()->exists());
    }

    public function test_content_seeders_are_safe_to_run_twice(): void
    {
        $this->seed([
            PromotionalTermSeeder::class,
            CategoryGameSeeder::class,
            NewsSeeder::class,
            GallerySeeder::class,
        ]);

        $categoryCount = Category::count();
        $gameCount = Game::count();
        $newsCount = News::count();
        $galleryCount = Gallery::count();

        $this->seed([
            PromotionalTermSeeder::class,
            CategoryGameSeeder::class,
            NewsSeeder::class,
            GallerySeeder::class,
        ]);

        $this->assertSame($categoryCount, Category::count());
        $this->assertSame($gameCount, Game::count());
        $this->assertSame($newsCount, News::count());
        $this->assertSame($galleryCount, Gallery::count());
        $this->assertDatabaseCount('promotional_terms', 1);
    }
}
