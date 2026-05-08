<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\MatchCompleted;
use App\Jobs\PlatformFeeJob;
use App\Models\FinalSupport;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WinnerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_declaring_a_winner_broadcasts_match_completed_once_to_all_users(): void
    {
        Event::fake([MatchCompleted::class]);
        Bus::fake([PlatformFeeJob::class]);

        $this->seedRoles();

        $admin = $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $viewer = $this->createUserWithRole(UserRole::USER, 'viewer@example.com');
        $artist = $this->createUserWithRole(UserRole::ARTIST, 'artist@example.com');
        $playerOne = User::factory()->create(['email' => 'player-one@example.com']);
        $playerTwo = User::factory()->create(['email' => 'player-two@example.com']);

        UserBalance::create(['user_id' => $viewer->id]);

        $match = GameMatch::create([
            'match_no' => '123456',
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'game_id' => '1',
            'type' => 'live',
            'winner_percentage' => 1,
            'loser_percentage' => 0,
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
            'user_id' => $viewer->id,
            'coin_amount' => 25,
            'result' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/match-winner/{$match->id}", [
                'winner_id' => $playerOne->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
            ]);

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'winner_id' => $playerOne->id,
            'type' => 'completed',
        ]);

        Event::assertDispatchedTimes(MatchCompleted::class, 1);
        Event::assertDispatched(MatchCompleted::class, function (MatchCompleted $event) use ($viewer, $artist) {
            $channels = collect($event->broadcastOn())
                ->map(fn (object $channel) => $channel->name)
                ->all();

            return in_array('private-user.'.$viewer->id, $channels, true)
                && in_array('private-user.'.$artist->id, $channels, true)
                && $event->broadcastAs() === 'match.completed'
                && $event->broadcastWith()['message'] === 'Match is over.';
        });

        Bus::assertDispatched(PlatformFeeJob::class);
    }

    private function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    private function createUserWithRole(UserRole $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    private function authHeadersFor(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser($user),
        ];
    }
}
