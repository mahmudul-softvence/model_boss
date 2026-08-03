<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Game;
use Illuminate\Database\Seeder;

class CategoryGameSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Sports' => [
                'EA Sports FC 25',
                'NBA 2K25',
                'Madden NFL 25',
            ],
            'FPS' => [
                'Call of Duty: Warzone',
                'Valorant',
                'Counter-Strike 2',
            ],
            'Battle Royale' => [
                'Fortnite',
                'Apex Legends',
                'PUBG: Battlegrounds',
            ],
            'Fighting' => [
                'Tekken 8',
                'Street Fighter 6',
                'Mortal Kombat 1',
            ],
            'Racing' => [
                'Forza Horizon 5',
                'Gran Turismo 7',
                'Rocket League',
            ],
        ];

        foreach ($catalog as $categoryName => $games) {
            $category = Category::updateOrCreate(
                ['name' => $categoryName],
                ['image' => null],
            );

            foreach ($games as $gameName) {
                Game::updateOrCreate(
                    ['name' => $gameName],
                    [
                        'category_id' => $category->id,
                        'image' => null,
                    ],
                );
            }
        }
    }
}
