<?php

namespace Tests\Feature;

use App\Events\SupportPlaced;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupportStoreTest extends TestCase
{
    use RefreshDatabase;

    private function createMatch(User $playerOne, User $playerTwo): GameMatch
    {
        return GameMatch::create([
            'match_no' => '654321',
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'type' => 'upcoming',
            'winner_percentage' => 1,
            'loser_percentage' => 0,
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 100,
            'player_two_total' => 100,
            'confirmation_status' => 0,
        ]);
    }

    public function test_placing_support_deducts_balance_and_updates_match_total(): void
    {
        Event::fake([SupportPlaced::class]);

        $playerOne = User::factory()->create();
        $playerTwo = User::factory()->create();
        $supporter = User::factory()->create();

        UserBalance::create(['user_id' => $supporter->id, 'total_balance' => 100]);

        $match = $this->createMatch($playerOne, $playerTwo);

        $response = $this->actingAs($supporter, 'api')
            ->postJson('/api/support', [
                'match_id' => $match->id,
                'supported_player_id' => $playerOne->id,
                'coin_amount' => 40,
            ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Support placed successfully',
            ]);

        $this->assertEqualsWithDelta(60.0, (float) $response->json('data.updated_balance'), 0.0001);
        $this->assertEqualsWithDelta(40.0, (float) $response->json('data.updated_total_bet'), 0.0001);
        $this->assertEqualsWithDelta(140.0, (float) $response->json('data.match_player_one_total'), 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $response->json('data.match_player_two_total'), 0.0001);
        $this->assertSame($supporter->id, $response->json('data.top_supporters.0.user_id'));

        $this->assertDatabaseHas('supports', [
            'match_id' => $match->id,
            'user_id' => $supporter->id,
            'supported_player_id' => $playerOne->id,
            'result' => 'pending',
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $supporter->id,
            'type' => 'support',
            'reference' => 'Support for match #'.$match->match_no,
        ]);

        Event::assertDispatched(SupportPlaced::class);
    }

    public function test_support_is_rejected_for_a_player_outside_the_match(): void
    {
        Event::fake([SupportPlaced::class]);

        $playerOne = User::factory()->create();
        $playerTwo = User::factory()->create();
        $outsider = User::factory()->create();
        $supporter = User::factory()->create();

        UserBalance::create(['user_id' => $supporter->id, 'total_balance' => 100]);

        $match = $this->createMatch($playerOne, $playerTwo);

        $this->actingAs($supporter, 'api')
            ->postJson('/api/support', [
                'match_id' => $match->id,
                'supported_player_id' => $outsider->id,
                'coin_amount' => 40,
            ])
            ->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Invalid supported player',
            ]);

        $this->assertEqualsWithDelta(100.0, (float) UserBalance::where('user_id', $supporter->id)->value('total_balance'), 0.0001);
        $this->assertDatabaseCount('supports', 0);
        Event::assertNotDispatched(SupportPlaced::class);
    }

    public function test_support_is_rejected_with_insufficient_balance(): void
    {
        Event::fake([SupportPlaced::class]);

        $playerOne = User::factory()->create();
        $playerTwo = User::factory()->create();
        $supporter = User::factory()->create();

        UserBalance::create(['user_id' => $supporter->id, 'total_balance' => 10]);

        $match = $this->createMatch($playerOne, $playerTwo);

        $this->actingAs($supporter, 'api')
            ->postJson('/api/support', [
                'match_id' => $match->id,
                'supported_player_id' => $playerOne->id,
                'coin_amount' => 40,
            ])
            ->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Insufficient balance',
            ]);

        $this->assertEqualsWithDelta(10.0, (float) UserBalance::where('user_id', $supporter->id)->value('total_balance'), 0.0001);
        $this->assertDatabaseCount('supports', 0);
    }
}
