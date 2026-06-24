<?php

namespace Tests\Feature;

use App\Enums\ChallengeStatus;
use App\Enums\UserRole;
use App\Jobs\ChallengeOfferExpiredJob;
use App\Models\Challenge;
use App\Models\Game;
use App\Models\User;
use App\Models\UserBalance;
use App\Notifications\ChallengeAcceptedNotification;
use App\Notifications\ChallengeApprovedNotification;
use App\Notifications\ChallengeOfferNotification;
use App\Notifications\ChallengeRejectedNotification;
use App\Services\ChallengeEscrowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Bus::fake([ChallengeOfferExpiredJob::class]);

        $this->seedRoles();
    }

    public function test_creating_a_challenge_reserves_the_stake_and_stays_pending(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com'); // id 1
        $game = $this->createGame();

        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $target = $this->player('target@example.com', balance: 1000);

        $response = $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $target, amount: 300));

        $response->assertCreated()
            ->assertJsonPath('data.amount_deducted', 300)
            ->assertJsonPath('data.remaining_balance', '700.00');

        $this->assertDatabaseHas('challenges', [
            'challenger_id' => $challenger->id,
            'target_player_id' => $target->id,
            'amount' => 300,
            'status' => ChallengeStatus::PENDING->value,
        ]);

        $this->assertSame(700.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $challenger->id,
            'type' => 'challenge-hold',
            'amount' => -300,
        ]);

        // Not visible publicly until approved.
        $this->getJson('/api/challenges')->assertJsonPath('meta.total', 0);
    }

    public function test_users_without_permission_cannot_create_a_challenge(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $game = $this->createGame();

        $challenger = $this->player('nope@example.com', balance: 1000, canCreate: false);
        $target = $this->player('target@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $target))
            ->assertForbidden();

        $this->assertDatabaseCount('challenges', 0);
        $this->assertSame(1000.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));
    }

    public function test_admin_approval_makes_the_offer_visible_and_acceptable(): void
    {
        $admin = $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $target = $this->player('target@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $target));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::OFFERED->value,
        ]);

        $this->getJson('/api/challenges')->assertJsonPath('meta.total', 1);

        Notification::assertSentTo($challenger, ChallengeApprovedNotification::class);
        Notification::assertSentTo($target, ChallengeOfferNotification::class);
    }

    public function test_admin_rejection_refunds_the_challenger(): void
    {
        $admin = $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $target = $this->player('target@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $target, amount: 300));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/reject")
            ->assertOk();

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::REJECTED->value,
        ]);

        $this->assertSame(1000.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $challenger->id,
            'type' => 'challenge-refund',
            'amount' => 300,
        ]);

        Notification::assertSentTo($challenger, ChallengeRejectedNotification::class);
    }

    public function test_a_challenge_cannot_be_accepted_before_approval(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $target = $this->player('target@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $target));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($target))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true])
            ->assertStatus(400);

        $this->assertSame(1000.0, (float) UserBalance::where('user_id', $target->id)->value('total_balance'));
    }

    public function test_winner_settlement_pays_pool_minus_fifteen_percent(): void
    {
        $admin = $this->platformAdmin(); // user id 1 — the account that collects the fee

        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $acceptor = $this->player('acceptor@example.com', balance: 1000);

        // Create -> approve -> accept -> declare winner
        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $acceptor, amount: 300))
            ->assertCreated();

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve")
            ->assertOk();

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true])
            ->assertOk();

        Notification::assertSentTo($challenger, ChallengeAcceptedNotification::class);
        $this->assertSame(700.0, (float) UserBalance::where('user_id', $acceptor->id)->value('total_balance'));

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/winner", [
                'winner_id' => $challenger->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.pool', 600)
            ->assertJsonPath('data.winner_payout', 510)
            ->assertJsonPath('data.admin_fee', 90);

        // Winner: 700 (after own hold) + 510 = 1210
        $this->assertSame(1210.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));
        // Admin keeps the 15% = 90
        $this->assertSame(90.0, (float) UserBalance::where('user_id', $admin->id)->value('total_balance'));
        // Conservation: 510 + 90 == 600
        $this->assertSame(600.0, 510.0 + 90.0);

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::COMPLETED->value,
            'winner_id' => $challenger->id,
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $challenger->id,
            'type' => 'challenge-win',
            'amount' => 510,
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $admin->id,
            'type' => 'challenge-fee',
            'amount' => 90,
        ]);
    }

    public function test_offer_expiry_refunds_the_challenger(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $challenger = $this->player('challenger@example.com', balance: 700);
        $target = $this->player('target@example.com', balance: 1000);

        $challenge = Challenge::factory()->offered()->create([
            'challenger_id' => $challenger->id,
            'target_player_id' => $target->id,
            'amount' => 300,
            'offer_expires_at' => now()->subMinute(),
        ]);

        (new ChallengeOfferExpiredJob($challenge->id))->handle(app(ChallengeEscrowService::class));

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::EXPIRED->value,
        ]);

        $this->assertSame(1000.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));
    }

    public function test_public_list_is_ordered_by_amount_desc_with_ranks(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');

        foreach ([5000, 10000, 7500] as $amount) {
            Challenge::factory()->offered()->create(['amount' => $amount]);
        }

        $response = $this->getJson('/api/challenges')->assertOk();

        $amounts = collect($response->json('data'))->pluck('amount')->map(fn ($a) => (float) $a)->all();
        $ranks = collect($response->json('data'))->pluck('rank')->all();

        $this->assertSame([10000.0, 7500.0, 5000.0], $amounts);
        $this->assertSame([1, 2, 3], $ranks);
    }

    public function test_target_player_can_list_incoming_challenges(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');

        $target = $this->player('target@example.com', balance: 1000);
        $other = $this->player('other@example.com', balance: 1000);

        // Two live offers addressed to the target...
        Challenge::factory()->offered()->count(2)->create(['target_player_id' => $target->id]);
        // ...one addressed to someone else...
        Challenge::factory()->offered()->create(['target_player_id' => $other->id]);
        // ...and one still pending admin approval for the target.
        Challenge::factory()->create(['target_player_id' => $target->id]);

        $response = $this->withHeaders($this->authHeadersFor($target))
            ->getJson('/api/challenges-for-me')
            ->assertOk();

        // Default view shows only live (offered) offers addressed to the target.
        $response->assertJsonPath('meta.total', 2);

        foreach ($response->json('data') as $row) {
            $this->assertSame($target->id, $row['target_player']['id']);
            $this->assertSame(ChallengeStatus::OFFERED->value, $row['status']);
        }

        // status=all also includes the pending one.
        $this->withHeaders($this->authHeadersFor($target))
            ->getJson('/api/challenges-for-me?status=all')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_player_payload_prefers_artist_name_then_falls_back_to_real_name(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');

        // Target with an artist name set.
        $withArtist = $this->player('artist@example.com', balance: 1000);
        $withArtist->update(['artist_name' => 'Stage Star']);

        // Target without an artist name; name parts drive the real name.
        $withoutArtist = $this->player('real@example.com', balance: 1000);
        $withoutArtist->update([
            'first_name' => 'Real',
            'middle_name' => null,
            'last_name' => 'Name',
            'artist_name' => null,
        ]);

        Challenge::factory()->offered()->create(['target_player_id' => $withArtist->id]);
        Challenge::factory()->offered()->create(['target_player_id' => $withoutArtist->id]);

        $artistRow = $this->withHeaders($this->authHeadersFor($withArtist))
            ->getJson('/api/challenges-for-me')
            ->assertOk()
            ->json('data.0.target_player');

        $this->assertSame('Stage Star', $artistRow['name']);

        $realRow = $this->withHeaders($this->authHeadersFor($withoutArtist))
            ->getJson('/api/challenges-for-me')
            ->assertOk()
            ->json('data.0.target_player');

        $this->assertSame($withoutArtist->fresh()->name, $realRow['name']);
        $this->assertSame('Real Name', $realRow['name']);
    }

    public function test_challenge_offer_notifies_the_target_via_mail_database_and_broadcast(): void
    {
        $admin = $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $target = $this->player('target@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $target));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve")
            ->assertOk();

        Notification::assertSentTo(
            $target,
            ChallengeOfferNotification::class,
            fn ($notification, array $channels) => in_array('mail', $channels, true)
                && in_array('database', $channels, true)
                && in_array('broadcast', $channels, true),
        );
    }

    // Helpers ---------------------------------------------------------------

    private function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    private function createUserWithRole(UserRole $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * The platform/admin account that collects the challenge fee. Forced to id 1
     * to match ChallengeSettlementService (transaction rollbacks do not reset the
     * MySQL auto-increment counter, so a freshly created user is not id 1).
     */
    private function platformAdmin(): User
    {
        $admin = User::factory()->make(['email' => 'admin@example.com']);
        $admin->id = 1;
        $admin->save();
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        UserBalance::create(['user_id' => 1, 'total_balance' => 0]);

        return $admin;
    }

    private function player(string $email, float $balance = 0, bool $canCreate = true): User
    {
        $user = User::factory()->create(['email' => $email, 'is_challenger' => $canCreate]);
        $user->assignRole(UserRole::USER->value);

        UserBalance::create(['user_id' => $user->id, 'total_balance' => $balance]);

        return $user;
    }

    private function createGame(): Game
    {
        return Game::create([
            'name' => 'Test Game '.uniqid(),
            'category_id' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function offerPayload(Game $game, User $target, float $amount = 300): array
    {
        return [
            'game_id' => $game->id,
            'amount' => $amount,
            'match_date' => now()->addDay()->toDateString(),
            'match_time' => '18:00',
            'mode' => 'unique',
            'target_player_id' => $target->id,
            'show_real_name' => true,
            'memo' => 'Lets go',
        ];
    }

    private function authHeadersFor(User $user): array
    {
        // Authenticate via the api guard for the next request. Using actingAs (rather
        // than a Bearer token) avoids the JWT guard caching the first authenticated
        // user across the multiple requests made within a single test method.
        $this->actingAs($user, 'api');

        return [];
    }
}
