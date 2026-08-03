<?php

namespace Database\Seeders;

use App\Models\News;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'title' => 'Welcome to Model Boss',
                'description' => 'Model Boss is live. Create your profile, connect your game, and start competing in challenges with real stakes and real bragging rights.',
                'status' => 'published',
                'is_featured' => true,
            ],
            [
                'title' => 'How Challenges Work',
                'description' => 'Pick a game, set your stake, and send or accept a challenge. Once both players are ready, the match goes live and the community can follow the action.',
                'status' => 'published',
                'is_featured' => true,
            ],
            [
                'title' => 'Tips, Referrals, and Rewards',
                'description' => 'Earn from wins, tips, and referrals. Keep your payout method connected so you can withdraw smoothly when you are ready to cash out.',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'title' => 'Community Guidelines',
                'description' => 'Play fair, keep matches honest, and respect other players. Accounts that abuse the platform, fix matches, or harass others may be suspended.',
                'status' => 'published',
                'is_featured' => false,
            ],
        ];

        foreach ($articles as $article) {
            News::updateOrCreate(
                ['title' => $article['title']],
                $article,
            );
        }
    }
}
