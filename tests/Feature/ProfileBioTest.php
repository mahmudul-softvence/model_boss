<?php

namespace Tests\Feature;

use App\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileBioTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_bio(): void
    {
        $user = User::factory()->create(['bio' => null]);

        $response = $this->actingAs($user, 'api')->patchJson(
            '/api/profile/bio',
            [
                'bio' => 'Competitive gamer and streamer.',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.bio', 'Competitive gamer and streamer.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bio' => 'Competitive gamer and streamer.',
        ]);
    }

    public function test_user_can_clear_their_bio(): void
    {
        $user = User::factory()->create(['bio' => 'Old bio']);

        $response = $this->actingAs($user, 'api')->patchJson(
            '/api/profile/bio',
            [
                'bio' => null,
            ],
        );

        $response->assertOk()->assertJsonPath('data.bio', null);

        $this->assertNull($user->fresh()->bio);
    }

    public function test_bio_update_requires_authentication(): void
    {
        $response = $this->patchJson('/api/profile/bio', [
            'bio' => 'Guest bio',
        ]);

        $response->assertUnauthorized();
    }

    public function test_bio_update_validates_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->patchJson(
            '/api/profile/bio',
            [
                'bio' => str_repeat('a', 1001),
            ],
        );

        $response->assertUnprocessable()->assertJsonValidationErrors('bio');
    }

    public function test_profile_resource_includes_bio_and_completed_challenge_record_counts(): void
    {
        $profileUser = User::factory()->create([
            'bio' => 'Ready for any challenge.',
        ]);
        $profileUser->userBalance()->create();

        $viewer = User::factory()->create();
        $opponent = User::factory()->create();

        Challenge::factory()->create([
            'challenger_id' => $profileUser->id,
            'target_player_id' => $opponent->id,
            'accepted_by_user_id' => $opponent->id,
            'status' => ChallengeStatus::COMPLETED->value,
            'winner_id' => $profileUser->id,
            'settled_at' => now(),
        ]);

        Challenge::factory()->create([
            'challenger_id' => $profileUser->id,
            'target_player_id' => $opponent->id,
            'accepted_by_user_id' => $opponent->id,
            'status' => ChallengeStatus::COMPLETED->value,
            'winner_id' => $opponent->id,
            'settled_at' => now(),
        ]);

        Challenge::factory()->create([
            'challenger_id' => $opponent->id,
            'target_player_id' => $profileUser->id,
            'accepted_by_user_id' => $profileUser->id,
            'status' => ChallengeStatus::COMPLETED->value,
            'winner_id' => $opponent->id,
            'settled_at' => now(),
        ]);

        Challenge::factory()->create([
            'challenger_id' => $profileUser->id,
            'target_player_id' => $opponent->id,
            'accepted_by_user_id' => $opponent->id,
            'status' => ChallengeStatus::ACCEPTED->value,
            'winner_id' => $profileUser->id,
        ]);

        $response = $this->actingAs($viewer, 'api')->getJson(
            "/api/show_artist_prifile/{$profileUser->id}",
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.user.bio', 'Ready for any challenge.')
            ->assertJsonPath('data.user.challenge_wins_count', 1)
            ->assertJsonPath('data.user.challenge_losses_count', 2);
    }
}
