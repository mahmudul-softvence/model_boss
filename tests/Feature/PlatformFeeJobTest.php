<?php

namespace Tests\Feature;

use App\Jobs\PlatformFeeJob;
use App\Models\FinalSupport;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\UserBalance;
use Tests\TestCase;

class PlatformFeeJobTest extends TestCase
{
    public function test_platform_fee_is_distributed_between_players_referrer_and_admin(): void
    {
        $admin = User::factory()->create(['id' => 1, 'email' => 'admin@example.com']);
        $playerOne = User::factory()->create(['email' => 'player-one@example.com']);
        $playerTwo = User::factory()->create(['email' => 'player-two@example.com']);
        $referrer = User::factory()->create(['email' => 'referrer@example.com']);
        $supporter = User::factory()->create([
            'email' => 'supporter@example.com',
            'referral_user_id' => $referrer->id,
            'reference_status' => 0,
        ]);

        foreach ([$admin, $playerOne, $playerTwo, $referrer, $supporter] as $user) {
            UserBalance::create(['user_id' => $user->id]);
        }

        $match = GameMatch::create([
            'match_no' => '123456',
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'type' => 'completed',
            'winner_id' => $playerOne->id,
            'winner_percentage' => 1,
            'loser_percentage' => 1,
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 150,
            'player_two_total' => 100,
            'confirmation_status' => 1,
        ]);

        FinalSupport::create([
            'support_id' => 1,
            'match_id' => $match->id,
            'match_no' => $match->match_no,
            'supported_player_id' => $playerOne->id,
            'user_id' => $supporter->id,
            'coin_amount' => 25,
            'result' => 'win',
        ]);

        // Fee of 15 splits as: winner 2/15, loser 1/15, referral pool 1/15, admin 11/15.
        // The supporter backed 25 of the 50-coin winning pool, so the referrer
        // earns half the referral pool and the rest returns to the admin.
        (new PlatformFeeJob(15, $match->id))->handle();

        $this->assertEqualsWithDelta(2.0, (float) UserBalance::where('user_id', $playerOne->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(1.0, (float) UserBalance::where('user_id', $playerTwo->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(0.5, (float) UserBalance::where('user_id', $referrer->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(0.5, (float) UserBalance::where('user_id', $referrer->id)->value('total_referral_earning'), 0.0001);
        $this->assertEqualsWithDelta(11.5, (float) UserBalance::where('user_id', $admin->id)->value('total_balance'), 0.0001);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $playerOne->id,
            'type' => 'match',
            'reference' => 'Match Commission #123456',
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $playerTwo->id,
            'type' => 'match',
            'reference' => 'Match Commission #123456',
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $referrer->id,
            'type' => 'referral',
            'reference' => 'Referral Match #123456',
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $admin->id,
            'type' => 'match',
            'reference' => 'Platform Fee #123456',
        ]);

        $this->assertSame(1, (int) $supporter->fresh()->reference_status);
    }

    public function test_nothing_is_distributed_when_the_match_has_no_winner(): void
    {
        $admin = User::factory()->create(['id' => 1, 'email' => 'admin@example.com']);
        $playerOne = User::factory()->create(['email' => 'player-one@example.com']);
        $playerTwo = User::factory()->create(['email' => 'player-two@example.com']);

        foreach ([$admin, $playerOne, $playerTwo] as $user) {
            UserBalance::create(['user_id' => $user->id]);
        }

        $match = GameMatch::create([
            'match_no' => '123457',
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'type' => 'live',
            'winner_percentage' => 1,
            'loser_percentage' => 1,
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 150,
            'player_two_total' => 100,
            'confirmation_status' => 1,
        ]);

        (new PlatformFeeJob(15, $match->id))->handle();

        $this->assertDatabaseCount('coin_transactions', 0);
        $this->assertEqualsWithDelta(0.0, (float) UserBalance::where('user_id', $admin->id)->value('total_balance'), 0.0001);
    }
}
