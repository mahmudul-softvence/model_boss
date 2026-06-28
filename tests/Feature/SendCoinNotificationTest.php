<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserBalance;
use App\Notifications\CoinReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendCoinNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The controller treats user id 1 as the admin fee account, so it must exist.
     */
    private function createAdmin(): User
    {
        $admin = User::factory()->create(['id' => 1]);
        UserBalance::create(['user_id' => $admin->id, 'total_balance' => 0]);

        return $admin;
    }

    public function test_sending_coins_notifies_the_receiver_with_artist_name(): void
    {
        Notification::fake();

        $this->createAdmin();
        $sender = User::factory()->create(['artist_name' => 'Stage Star']);
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $sender->id, 'total_balance' => 1000]);
        UserBalance::create(['user_id' => $receiver->id, 'total_balance' => 0]);

        $this->actingAs($sender, 'api')
            ->postJson('/api/send-coin', [
                'receiver_id' => $receiver->id,
                'amount' => 100,
            ])
            ->assertStatus(200)
            ->assertJson(['status' => true]);

        // amount 100 => fee 10 => receiver gets 90 coins.
        Notification::assertSentTo(
            $receiver,
            CoinReceivedNotification::class,
            function (CoinReceivedNotification $notification, array $channels) use ($receiver, $sender) {
                $this->assertEqualsCanonicalizing(['mail', 'database', 'broadcast'], $notification->via($receiver));

                $payload = $notification->toDatabase($receiver);

                return in_array('mail', $channels)
                    && in_array('database', $channels)
                    && in_array('broadcast', $channels)
                    && $payload['sender_id'] === $sender->id
                    && $payload['sender_name'] === 'Stage Star'
                    && (float) $payload['amount'] === 90.0;
            }
        );

        Notification::assertNotSentTo($sender, CoinReceivedNotification::class);
    }

    public function test_notification_falls_back_to_real_name_without_artist_name(): void
    {
        Notification::fake();

        $this->createAdmin();
        // The saving hook rebuilds `name` from the name parts, so set those.
        $sender = User::factory()->create([
            'first_name' => 'Real',
            'middle_name' => null,
            'last_name' => 'Name',
            'artist_name' => null,
        ]);
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $sender->id, 'total_balance' => 1000]);
        UserBalance::create(['user_id' => $receiver->id, 'total_balance' => 0]);

        $this->actingAs($sender, 'api')
            ->postJson('/api/send-coin', [
                'receiver_id' => $receiver->id,
                'amount' => 100,
            ])
            ->assertStatus(200)
            ->assertJson(['status' => true]);

        Notification::assertSentTo(
            $receiver,
            CoinReceivedNotification::class,
            function (CoinReceivedNotification $notification) use ($receiver, $sender) {
                return $notification->toDatabase($receiver)['sender_name'] === $sender->name;
            }
        );
    }

    public function test_sending_coins_succeeds_when_receiver_has_no_balance_row(): void
    {
        Notification::fake();

        $this->createAdmin();
        $sender = User::factory()->create();
        // Receiver intentionally has no user_balances row.
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $sender->id, 'total_balance' => 1000]);

        $this->actingAs($sender, 'api')
            ->postJson('/api/send-coin', [
                'receiver_id' => $receiver->id,
                'amount' => 100,
            ])
            ->assertStatus(200)
            ->assertJson(['status' => true]);

        // amount 100 => fee 10 => receiver gets 90 coins in a freshly created row.
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $receiver->id,
            'total_balance' => 90,
        ]);

        Notification::assertSentTo($receiver, CoinReceivedNotification::class);
    }

    public function test_no_notification_when_sending_coins_fails(): void
    {
        Notification::fake();

        $this->createAdmin();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $sender->id, 'total_balance' => 5]);
        UserBalance::create(['user_id' => $receiver->id, 'total_balance' => 0]);

        $this->actingAs($sender, 'api')
            ->postJson('/api/send-coin', [
                'receiver_id' => $receiver->id,
                'amount' => 100,
            ])
            ->assertStatus(400)
            ->assertJson(['status' => false]);

        Notification::assertNothingSent();
    }
}
