<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\User;
use Tests\TestCase;

class MatchLandingTest extends TestCase
{
    public function test_past_filter_returns_only_completed_matches(): void
    {
        User::factory()->create(); // id 1 — supplies the landing page model_picture
        $playerOne = User::factory()->create();
        $playerTwo = User::factory()->create();

        $base = [
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 100,
            'player_two_total' => 100,
        ];

        $completed = GameMatch::create($base + [
            'match_no' => '111111',
            'type' => 'completed',
            'confirmation_status' => 1,
            'winner_id' => $playerOne->id,
        ]);

        GameMatch::create($base + [
            'match_no' => '222222',
            'type' => 'live',
            'confirmation_status' => 1,
        ]);

        GameMatch::create($base + [
            'match_no' => '333333',
            'type' => 'upcoming',
            'confirmation_status' => 1,
        ]);

        GameMatch::create($base + [
            'match_no' => '444444',
            'type' => 'upcoming',
            'confirmation_status' => 2,
        ]);

        GameMatch::create($base + [
            'match_no' => '555555',
            'type' => 'unsettled',
            'confirmation_status' => 1,
        ]);

        $response = $this->getJson('/api/matches?type=past')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame($completed->id, $response->json('data.0.id'));
        $this->assertSame('completed', $response->json('data.0.type'));
    }
}
