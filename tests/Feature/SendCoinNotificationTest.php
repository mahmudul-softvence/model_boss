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

    public function test_sending_coins_notifies_the_receiver(): void
    {
        Notification::fake();

        // The controller treats user id 1 as the admin fee account.
        $admin = User::factory()->create();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $admin->id, 'total_balance' => 0]);
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
                    && (float) $payload['amount'] === 90.0;
            }
        );

        Notification::assertNotSentTo($sender, CoinReceivedNotification::class);
    }

    public function test_no_notification_when_sending_coins_fails(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $admin->id, 'total_balance' => 0]);
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
