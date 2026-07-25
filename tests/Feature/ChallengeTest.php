<?php

namespace Tests\Feature;

use App\Enums\ChallengeStatus;
use App\Enums\UserRole;
use App\Jobs\ChallengeOfferExpiredJob;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBalance;
use App\Notifications\ChallengeAcceptedNotification;
use App\Notifications\ChallengeApprovedNotification;
use App\Notifications\ChallengeLostNotification;
use App\Notifications\ChallengeOfferNotification;
use App\Notifications\ChallengeRejectedNotification;
use App\Notifications\ChallengeWonNotification;
use App\Services\ChallengeEscrowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
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
            ->assertJsonPath('data.remaining_balance', 700);

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

    public function test_auto_offer_setting_publishes_new_challenges_without_admin_approval(): void
    {
        $admin = $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $target = $this->player('target@example.com', balance: 1000);

        Setting::create(['key' => 'auto_offer_challenges', 'value' => 'true']);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $target, amount: 300))
            ->assertCreated()
            ->assertJsonPath('message', 'Challenge created and is now live.');

        $challenge = Challenge::first();

        $this->assertSame(ChallengeStatus::OFFERED->value, $challenge->status->value);
        $this->assertNotNull($challenge->approved_at);

        // Immediately visible/acceptable on the public list, no admin step.
        $this->getJson('/api/challenges')->assertJsonPath('meta.total', 1);

        Notification::assertSentTo($challenger, ChallengeApprovedNotification::class);
        Notification::assertSentTo($target, ChallengeOfferNotification::class);
        Notification::assertNotSentTo($admin, ChallengeCreatedAdminNotification::class);
    }

    public function test_admin_can_toggle_the_auto_offer_challenges_setting(): void
    {
        $admin = $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');

        // Defaults to false when never set.
        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/settings/auto_offer_challenges')
            ->assertOk()
            ->assertJsonPath('data.value', 'false');

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/auto_offer_challenges', ['value' => 'true'])
            ->assertOk()
            ->assertJsonPath('data.value', 'true');

        $this->assertDatabaseHas('settings', [
            'key' => 'auto_offer_challenges',
            'value' => 'true',
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/settings/auto_offer_challenges')
            ->assertJsonPath('data.value', 'true');
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

        Notification::assertSentTo(
            $challenger,
            ChallengeWonNotification::class,
            function (ChallengeWonNotification $notification, array $channels) use ($challenger) {
                return $channels === ['mail', 'database', 'broadcast']
                    && $notification->toDatabase($challenger)['payout'] === 510.0;
            }
        );

        Notification::assertSentTo(
            $acceptor,
            ChallengeLostNotification::class,
            function (ChallengeLostNotification $notification, array $channels) use ($acceptor) {
                return $channels === ['mail', 'database', 'broadcast']
                    && $notification->toDatabase($acceptor)['stake'] === 300.0;
            }
        );
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

    public function test_expired_offer_cannot_be_accepted_and_exposes_expiry_state(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $target = $this->player('target@example.com', balance: 1000);

        // An offered challenge whose window has already closed (the background
        // expiry job has not yet flipped the status).
        $challenge = Challenge::factory()->offered()->create([
            'challenger_id' => $challenger->id,
            'target_player_id' => $target->id,
            'amount' => 300,
            'offer_expires_at' => now()->subMinute(),
        ]);

        // The dashboard payload tells the frontend to disable acceptance.
        $this->withHeaders($this->authHeadersFor($target))
            ->getJson('/api/challenges-for-me')
            ->assertOk()
            ->assertJsonPath('data.0.is_expired', true)
            ->assertJsonPath('data.0.can_accept', false)
            ->assertJsonPath('data.0.expiry_message', 'This challenge offer has expired.');

        // And the server rejects a late acceptance attempt.
        $this->withHeaders($this->authHeadersFor($target))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true])
            ->assertStatus(400)
            ->assertJsonPath('message', 'This challenge offer has expired.');

        // No stake was taken from the target.
        $this->assertSame(1000.0, (float) UserBalance::where('user_id', $target->id)->value('total_balance'));
    }

    public function test_live_offer_can_be_accepted_and_reports_accept_state(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $target = $this->player('target@example.com', balance: 1000);

        Challenge::factory()->offered()->create([
            'target_player_id' => $target->id,
            'offer_expires_at' => now()->addHour(),
        ]);

        $this->withHeaders($this->authHeadersFor($target))
            ->getJson('/api/challenges-for-me')
            ->assertOk()
            ->assertJsonPath('data.0.is_expired', false)
            ->assertJsonPath('data.0.can_accept', true)
            ->assertJsonPath('data.0.expiry_message', null);
    }

    public function test_public_list_hides_expired_offers_but_incoming_dashboard_keeps_them(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');
        $target = $this->player('target@example.com', balance: 1000);

        // A live offer and an expired-but-not-yet-flipped offer, both addressed
        // to the same target.
        Challenge::factory()->offered()->create([
            'target_player_id' => $target->id,
            'amount' => 5000,
            'offer_expires_at' => now()->addHour(),
        ]);
        Challenge::factory()->offered()->create([
            'target_player_id' => $target->id,
            'amount' => 7000,
            'offer_expires_at' => now()->subMinute(),
        ]);

        // Public ranked list shows only the live offer.
        $this->getJson('/api/challenges')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', '5000.00');

        // The target's own dashboard still shows both, the expired one disabled.
        $this->withHeaders($this->authHeadersFor($target))
            ->getJson('/api/challenges-for-me')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_public_list_shows_only_offered_challenges(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');

        Challenge::factory()->offered()->create(['amount' => 5000]);
        Challenge::factory()->create([
            'status' => ChallengeStatus::ACCEPTED->value,
            'amount' => 9000,
        ]);
        Challenge::factory()->create([
            'status' => ChallengeStatus::COMPLETED->value,
            'amount' => 8000,
        ]);

        $this->getJson('/api/challenges')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', '5000.00')
            ->assertJsonPath('data.0.status', ChallengeStatus::OFFERED->value);
    }

    public function test_process_due_command_dispatches_expiry_only_for_overdue_open_offers(): void
    {
        $overdueOffered = Challenge::factory()->offered()->create([
            'offer_expires_at' => now()->subMinute(),
        ]);

        $overduePending = Challenge::factory()->create([
            'status' => ChallengeStatus::PENDING->value,
            'offer_expires_at' => now()->subMinute(),
        ]);

        // Still within its window, so it must not be swept.
        $stillOpen = Challenge::factory()->offered()->create([
            'offer_expires_at' => now()->addHour(),
        ]);

        // Already accepted, so it is no longer a refundable open offer.
        $accepted = Challenge::factory()->create([
            'status' => ChallengeStatus::ACCEPTED->value,
            'offer_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('challenges:process-due')->assertSuccessful();

        Bus::assertDispatched(ChallengeOfferExpiredJob::class, fn ($job) => $job->challengeId === $overdueOffered->id);
        Bus::assertDispatched(ChallengeOfferExpiredJob::class, fn ($job) => $job->challengeId === $overduePending->id);
        Bus::assertNotDispatched(ChallengeOfferExpiredJob::class, fn ($job) => $job->challengeId === $stillOpen->id);
        Bus::assertNotDispatched(ChallengeOfferExpiredJob::class, fn ($job) => $job->challengeId === $accepted->id);
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

    public function test_profile_lists_challenges_accepted_by_the_user(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');

        $acceptor = $this->player('acceptor@example.com', balance: 1000);
        $other = $this->player('other@example.com', balance: 1000);

        // Two challenges this user accepted...
        Challenge::factory()->count(2)->create([
            'accepted_by_user_id' => $acceptor->id,
            'status' => ChallengeStatus::ACCEPTED->value,
        ]);
        // ...one the user accepted but has since completed (excluded here)...
        Challenge::factory()->create([
            'accepted_by_user_id' => $acceptor->id,
            'status' => ChallengeStatus::COMPLETED->value,
        ]);
        // ...one accepted by someone else.
        Challenge::factory()->create([
            'accepted_by_user_id' => $other->id,
            'status' => ChallengeStatus::ACCEPTED->value,
        ]);

        $response = $this->getJson("/api/users/{$acceptor->id}/accepted-challenges")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        foreach ($response->json('data') as $row) {
            $this->assertSame($acceptor->id, $row['acceptor']['id']);
            $this->assertSame(ChallengeStatus::ACCEPTED->value, $row['status']);
        }
    }

    public function test_profile_lists_challenges_completed_by_the_user(): void
    {
        $this->createUserWithRole(UserRole::SUPER_ADMIN, 'admin@example.com');

        $acceptor = $this->player('acceptor@example.com', balance: 1000);
        $other = $this->player('other@example.com', balance: 1000);

        // Two challenges this user accepted and completed...
        Challenge::factory()->count(2)->create([
            'accepted_by_user_id' => $acceptor->id,
            'status' => ChallengeStatus::COMPLETED->value,
        ]);
        // ...one still in play (excluded here)...
        Challenge::factory()->create([
            'accepted_by_user_id' => $acceptor->id,
            'status' => ChallengeStatus::ACCEPTED->value,
        ]);
        // ...one completed by someone else.
        Challenge::factory()->create([
            'accepted_by_user_id' => $other->id,
            'status' => ChallengeStatus::COMPLETED->value,
        ]);

        $response = $this->getJson("/api/users/{$acceptor->id}/completed-challenges")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        foreach ($response->json('data') as $row) {
            $this->assertSame($acceptor->id, $row['acceptor']['id']);
            $this->assertSame(ChallengeStatus::COMPLETED->value, $row['status']);
        }
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

    public function test_show_exposes_the_platform_admin_as_the_model(): void
    {
        $admin = $this->platformAdmin();
        $admin->update([
            'name' => 'Platform Boss',
            'artist_name' => null,
            'image' => 'admins/boss.png',
        ]);
        $admin->refresh();

        $challenge = Challenge::factory()->offered()->create();

        $response = $this->getJson("/api/challenges/{$challenge->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $challenge->id);

        $response
            ->assertJsonPath('data.model.id', $admin->id)
            ->assertJsonPath('data.model.name', 'Platform Boss')
            ->assertJsonPath('data.model.image', $admin->image_url);

        $this->assertNotNull($admin->image_url);
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

    public function test_publish_match_creates_game_match_from_accepted_challenge(): void
    {
        $admin = $this->platformAdmin();
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $acceptor = $this->player('acceptor@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $acceptor, amount: 500));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve");

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true]);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", [
                'type' => 'upcoming',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.match_type', 'challenge');

        $this->assertDatabaseHas('game_matches', [
            'challenge_id' => $challenge->id,
            'player_one_id' => $challenger->id,
            'player_two_id' => $acceptor->id,
            'match_type' => 'challenge',
            'player_one_bet' => 500,
            'player_two_bet' => 500,
        ]);

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::ACCEPTED->value,
        ]);
    }

    public function test_published_challenge_includes_is_published_flag(): void
    {
        $admin = $this->platformAdmin();
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $acceptor = $this->player('acceptor@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $acceptor, amount: 300));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve");

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true]);

        // Before publish
        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/challenges')
            ->assertJsonPath('data.0.is_published', false)
            ->assertJsonPath('data.0.published_match_id', null);

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", [
                'type' => 'upcoming',
            ]);

        $match = GameMatch::where('challenge_id', $challenge->id)->first();

        // After publish the challenge exposes the published match number.
        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/challenges')
            ->assertJsonPath('data.0.is_published', true)
            ->assertJsonPath('data.0.published_match_id', $match->match_no);
    }

    public function test_admin_challenge_winner_blocked_when_challenge_is_published(): void
    {
        $admin = $this->platformAdmin();
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $acceptor = $this->player('acceptor@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $acceptor, amount: 300));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve");

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", [
                'type' => 'upcoming',
            ]);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/winner", [
                'winner_id' => $challenger->id,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'This challenge has been published as a match. Select the winner from the match management.');
    }

    public function test_winner_selection_on_challenge_match_sets_challenge_completed(): void
    {
        $admin = $this->platformAdmin();
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $acceptor = $this->player('acceptor@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $acceptor, amount: 300));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve");

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", [
                'type' => 'upcoming',
            ]);

        $match = GameMatch::where('challenge_id', $challenge->id)->first();

        $match->update(['confirmation_status' => 1]);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/match-winner/{$match->id}", [
                'winner_id' => $challenger->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'winner_id' => $challenger->id,
            'type' => 'completed',
        ]);

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::COMPLETED->value,
            'winner_id' => $challenger->id,
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $challenger->id,
            'type' => 'challenge-win',
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $admin->id,
            'type' => 'challenge-fee',
        ]);

        // 300 stake each -> 600 pool, 15% fee -> 510 credited to the winner.
        $this->assertSame(1210.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));

        Notification::assertSentTo($challenger, ChallengeWonNotification::class);
        Notification::assertSentTo($acceptor, ChallengeLostNotification::class);
    }

    public function test_publish_match_cannot_publish_the_same_challenge_twice(): void
    {
        [$admin,,, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming'])
            ->assertCreated();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'This challenge has already been published as a match.');

        $this->assertSame(1, GameMatch::where('challenge_id', $challenge->id)->count());
    }

    public function test_publish_match_ignores_injected_admin_fields(): void
    {
        [$admin, $challenger,, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", [
                'type' => 'upcoming',
                'winner_id' => $challenger->id,
                'confirmation_status' => 1,
                'pin_to_top' => 1,
                'remove_status' => 1,
                'player_one_bet' => 5,
                'voting_time' => now()->addDay()->toDateTimeString(),
            ])
            ->assertCreated();

        $match = GameMatch::where('challenge_id', $challenge->id)->first();

        $this->assertNull($match->winner_id);
        $this->assertSame(0, (int) $match->confirmation_status);
        $this->assertSame(0, (int) $match->pin_to_top);
        $this->assertSame(0, (int) $match->remove_status);
        $this->assertNull($match->voting_time);
        $this->assertSame(300.0, (float) $match->player_one_bet);
    }

    public function test_publish_match_rejects_a_type_other_than_upcoming(): void
    {
        [$admin,,, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'live'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed');

        $this->assertSame(0, GameMatch::where('challenge_id', $challenge->id)->count());
    }

    public function test_admin_cannot_cancel_a_challenge_with_an_active_published_match(): void
    {
        [$admin, $challenger,, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/cancel")
            ->assertStatus(400);

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::ACCEPTED->value,
        ]);

        // Stakes remain held.
        $this->assertSame(700.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));
    }

    public function test_admin_can_cancel_a_challenge_after_its_published_match_was_declined(): void
    {
        [$admin, $challenger, $acceptor, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming']);

        GameMatch::where('challenge_id', $challenge->id)->first()
            ->update(['confirmation_status' => 2, 'type' => 'unsettled']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::CANCELLED->value,
        ]);

        $this->assertSame(1000.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));
        $this->assertSame(1000.0, (float) UserBalance::where('user_id', $acceptor->id)->value('total_balance'));
    }

    public function test_admin_cannot_delete_a_challenge_with_an_active_published_match(): void
    {
        [$admin, $challenger,, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->deleteJson("/api/admin/challenges/{$challenge->id}")
            ->assertStatus(400);

        $this->assertDatabaseHas('challenges', ['id' => $challenge->id]);
        $this->assertSame(700.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));
    }

    public function test_deleting_a_published_match_without_support_unpublishes_the_challenge(): void
    {
        [$admin,,, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming']);

        $match = GameMatch::where('challenge_id', $challenge->id)->first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->deleteJson("/api/admin/matches/{$match->id}")
            ->assertOk();

        $this->assertDatabaseMissing('game_matches', ['id' => $match->id]);

        // The challenge is intact and no longer marked as published.
        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/challenges')
            ->assertJsonPath('data.0.is_published', false)
            ->assertJsonPath('data.0.published_match_id', null);
    }

    public function test_admin_list_orders_pending_then_offered_then_accepted_then_under_review_then_completed_then_cancelled(): void
    {
        $admin = $this->platformAdmin();

        // Created out of order on purpose; same amount so status decides the order.
        foreach (
            [
                ChallengeStatus::CANCELLED,
                ChallengeStatus::COMPLETED,
                ChallengeStatus::UNDER_REVIEW,
                ChallengeStatus::PENDING,
                ChallengeStatus::ACCEPTED,
                ChallengeStatus::OFFERED,
            ] as $status
        ) {
            Challenge::factory()->create(['status' => $status->value, 'amount' => 500]);
        }

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/challenges')
            ->assertOk();

        $statuses = collect($response->json('data'))->pluck('status')->all();

        $this->assertSame([
            ChallengeStatus::PENDING->value,
            ChallengeStatus::OFFERED->value,
            ChallengeStatus::ACCEPTED->value,
            ChallengeStatus::UNDER_REVIEW->value,
            ChallengeStatus::COMPLETED->value,
            ChallengeStatus::CANCELLED->value,
        ], $statuses);
    }

    public function test_public_match_list_can_filter_challenge_matches(): void
    {
        [$admin, $challenger, $acceptor, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming']);

        // A regular (non-challenge) match that must be excluded by the filter.
        GameMatch::create([
            'match_no' => '654321',
            'player_one_id' => $challenger->id,
            'player_two_id' => $acceptor->id,
            'game_id' => $challenge->game_id,
            'type' => 'upcoming',
            'confirmation_status' => 0,
            'player_one_bet' => 100,
            'player_two_bet' => 100,
            'player_one_total' => 100,
            'player_two_total' => 100,
        ]);

        // Without the filter both matches are listed.
        $this->getJson('/api/matches')->assertJsonPath('meta.total', 2);

        $response = $this->getJson('/api/matches?type=challenge')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame('challenge', $response->json('data.0.match_type'));
        $this->assertSame($challenge->id, $response->json('data.0.challenge_id'));
    }

    public function test_regular_challenge_match_starts_when_both_players_are_ready(): void
    {
        [, $challenger, $acceptor, $challenge] = $this->acceptedChallenge();

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson("/api/challenges/{$challenge->id}/ready", $this->readyPayload())
            ->assertOk()
            ->assertJsonPath('data.both_players_ready', false)
            ->assertJsonPath('data.started_at', null);

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/ready", $this->readyPayload())
            ->assertOk()
            ->assertJsonPath('data.both_players_ready', true);

        $challenge->refresh();

        $this->assertNotNull($challenge->challenger_ready_at);
        $this->assertNotNull($challenge->acceptor_ready_at);
        $this->assertNotNull($challenge->started_at);
    }

    public function test_players_submit_regular_challenge_results_for_admin_review(): void
    {
        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        [$admin, $challenger, $acceptor, $challenge] = $this->acceptedStartedChallenge();

        $this->withHeaders($this->authHeadersFor($challenger) + ['Accept' => 'application/json'])
            ->post("/api/challenges/{$challenge->id}/submit-result", [
                'score' => '21-18',
                'notes' => 'I won the match.',
                'evidence_image' => UploadedFile::fake()->image('score.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.submitted_for_review_at', fn ($v) => $v !== null);

        $this->withHeaders($this->authHeadersFor($acceptor) + ['Accept' => 'application/json'])
            ->post("/api/challenges/{$challenge->id}/submit-result", [
                'score' => '18-21',
                'notes' => 'Opponent score is correct.',
                'evidence_video' => UploadedFile::fake()->create('recording.mp4', 1000, 'video/mp4'),
            ])
            ->assertOk();

        $this->assertSame(2, ChallengeSubmission::where('challenge_id', $challenge->id)->count());
        $this->assertNotNull($challenge->fresh()->submitted_for_review_at);

        ChallengeSubmission::where('challenge_id', $challenge->id)->get()
            ->each(function (ChallengeSubmission $submission) {
                $imagePath = $submission->getRawOriginal('evidence_image');
                $videoPath = $submission->getRawOriginal('evidence_video');

                if ($imagePath) {
                    Storage::disk('s3')->assertExists($imagePath);
                }

                if ($videoPath) {
                    Storage::disk('s3')->assertExists($videoPath);
                }
            });

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson("/api/admin/challenges/{$challenge->id}/submissions")
            ->assertOk()
            ->assertJsonPath('data.submissions.0.user.id', $challenger->id)
            ->assertJsonPath('data.submissions.1.user.id', $acceptor->id);

        $this->assertCount(2, $response->json('data.submissions'));
    }

    public function test_regular_challenge_submission_flow_is_blocked_after_publishing_as_official_match(): void
    {
        [$admin, $challenger,, $challenge] = $this->acceptedStartedChallenge();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/publish-match", ['type' => 'upcoming'])
            ->assertCreated();

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson("/api/challenges/{$challenge->id}/submit-result", [
                'score' => '1-0',
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'This challenge is published as an official match. Use match management instead.');

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson("/api/admin/challenges/{$challenge->id}/submissions")
            ->assertStatus(400)
            ->assertJsonPath('message', 'This challenge is published as a match. Review it from match management.');
    }

    public function test_admin_declares_winner_after_regular_challenge_submissions(): void
    {
        [$admin, $challenger, $acceptor, $challenge] = $this->acceptedStartedChallenge();

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson("/api/challenges/{$challenge->id}/submit-result", [
                'score' => '21-18',
            ]);

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/submit-result", [
                'notes' => 'The submitted result is correct.',
            ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/winner", [
                'winner_id' => $challenger->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Winner declared and pool settled successfully.');

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'status' => ChallengeStatus::COMPLETED->value,
            'winner_id' => $challenger->id,
        ]);

        $this->assertNotNull($challenge->fresh()->admin_reviewed_at);
        $this->assertSame(1210.0, (float) UserBalance::where('user_id', $challenger->id)->value('total_balance'));
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

    /**
     * Create an approved challenge whose stake has been matched by the acceptor.
     *
     * @return array{0: User, 1: User, 2: User, 3: Challenge} [admin, challenger, acceptor, challenge]
     */
    private function acceptedChallenge(float $amount = 300): array
    {
        $admin = $this->platformAdmin();
        $game = $this->createGame();
        $challenger = $this->player('challenger@example.com', balance: 1000, canCreate: true);
        $acceptor = $this->player('acceptor@example.com', balance: 1000);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson('/api/challenges', $this->offerPayload($game, $acceptor, amount: $amount));

        $challenge = Challenge::first();

        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/challenges/{$challenge->id}/approve");

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/accept", ['terms_accepted' => true]);

        return [$admin, $challenger, $acceptor, $challenge];
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Challenge} [admin, challenger, acceptor, challenge]
     */
    private function acceptedStartedChallenge(float $amount = 300): array
    {
        [$admin, $challenger, $acceptor, $challenge] = $this->acceptedChallenge($amount);

        $this->withHeaders($this->authHeadersFor($challenger))
            ->postJson("/api/challenges/{$challenge->id}/ready", $this->readyPayload());

        $this->withHeaders($this->authHeadersFor($acceptor))
            ->postJson("/api/challenges/{$challenge->id}/ready", $this->readyPayload());

        return [$admin, $challenger, $acceptor, $challenge->fresh()];
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

    /**
     * @return array<string, bool>
     */
    private function readyPayload(): array
    {
        return [
            'battery_confirmed' => true,
            'internet_confirmed' => true,
            'camera_confirmed' => true,
            'rules_confirmed' => true,
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
