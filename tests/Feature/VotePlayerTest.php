<?php

namespace Tests\Feature;

use App\Events\MatchVoteUpdated;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VotePlayerTest extends TestCase
{

    public function test_voting_deducts_half_the_votes_from_balance_and_credits_admin(): void
    {
        Event::fake([MatchVoteUpdated::class]);

        $admin = User::factory()->create(['id' => 1]);
        $playerOne = User::factory()->create();
        $playerTwo = User::factory()->create();
        $voter = User::factory()->create();

        UserBalance::create(['user_id' => $admin->id, 'total_balance' => 0]);
        UserBalance::create(['user_id' => $voter->id, 'total_balance' => 100]);

        $match = GameMatch::create([
            'match_no' => '112233',
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'type' => 'live',
            'winner_percentage' => 1,
            'loser_percentage' => 0,
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 100,
            'player_two_total' => 100,
            'confirmation_status' => 1,
            'vote_start_time' => now()->subMinute(),
            'voting_time' => now()->addHour(),
        ]);

        $response = $this->actingAs($voter, 'api')
            ->postJson("/api/vote-player/{$match->id}", [
                'player_id' => $playerOne->id,
                'total_vote' => 10,
            ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Vote submitted successfully',
            ])
            ->assertJsonPath('data.match_id', $match->id)
            ->assertJsonPath('data.top_voters.0.user_id', $voter->id)
            ->assertJsonPath('data.top_voters.0.total_votes', 10);

        $this->assertEqualsWithDelta(95.0, (float) UserBalance::where('user_id', $voter->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) UserBalance::where('user_id', $admin->id)->value('total_balance'), 0.0001);

        $this->assertDatabaseHas('player_votes', [
            'user_id' => $voter->id,
            'voted_player_id' => $playerOne->id,
            'match_id' => $match->id,
            'total_vote' => 10,
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $voter->id,
            'type' => 'vote',
            'reference' => 'Vote for match ID: '.$match->id,
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $admin->id,
            'type' => 'vote',
            'reference' => 'Received vote from user ID: '.$voter->id,
        ]);

        Event::assertDispatched(MatchVoteUpdated::class);
    }

    public function test_voting_fails_when_voting_has_not_started(): void
    {
        Event::fake([MatchVoteUpdated::class]);

        $playerOne = User::factory()->create();
        $playerTwo = User::factory()->create();
        $voter = User::factory()->create();

        UserBalance::create(['user_id' => $voter->id, 'total_balance' => 100]);

        $match = GameMatch::create([
            'match_no' => '112234',
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'type' => 'live',
            'winner_percentage' => 1,
            'loser_percentage' => 0,
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 100,
            'player_two_total' => 100,
            'confirmation_status' => 1,
        ]);

        $this->actingAs($voter, 'api')
            ->postJson("/api/vote-player/{$match->id}", [
                'player_id' => $playerOne->id,
                'total_vote' => 10,
            ])
            ->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Voting has not started yet',
            ]);

        $this->assertDatabaseCount('player_votes', 0);
    }

    public function test_voting_fails_with_insufficient_balance(): void
    {
        Event::fake([MatchVoteUpdated::class]);

        $admin = User::factory()->create(['id' => 1]);
        $playerOne = User::factory()->create();
        $playerTwo = User::factory()->create();
        $voter = User::factory()->create();

        UserBalance::create(['user_id' => $admin->id, 'total_balance' => 0]);
        UserBalance::create(['user_id' => $voter->id, 'total_balance' => 2]);

        $match = GameMatch::create([
            'match_no' => '112235',
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'type' => 'live',
            'winner_percentage' => 1,
            'loser_percentage' => 0,
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 100,
            'player_two_total' => 100,
            'confirmation_status' => 1,
            'vote_start_time' => now()->subMinute(),
            'voting_time' => now()->addHour(),
        ]);

        $this->actingAs($voter, 'api')
            ->postJson("/api/vote-player/{$match->id}", [
                'player_id' => $playerOne->id,
                'total_vote' => 10,
            ])
            ->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Insufficient balance',
            ]);

        $this->assertEqualsWithDelta(2.0, (float) UserBalance::where('user_id', $voter->id)->value('total_balance'), 0.0001);
        $this->assertDatabaseCount('player_votes', 0);
    }
}
