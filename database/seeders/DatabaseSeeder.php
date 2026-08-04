<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            LiveStatusChange::class,
            SettingSeeder::class,
            SocialLinkSeeder::class,
            CredentialSettingSeeder::class,
            PromotionalTermSeeder::class,
            CategoryGameSeeder::class,
            NewsSeeder::class,
            GallerySeeder::class,
        ]);
    }
}
