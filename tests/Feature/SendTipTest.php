<?php

namespace Tests\Feature;

use App\Models\Tip;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendTipTest extends TestCase
{
    /**
     * The controller treats user id 1 as the admin fee account, so it must exist.
     */
    private function createAdmin(): User
    {
        $admin = User::factory()->create(['id' => 1]);
        UserBalance::create(['user_id' => $admin->id, 'total_balance' => 0]);

        return $admin;
    }

    public function test_tip_is_split_between_receiver_and_admin(): void
    {
        Notification::fake();

        $admin = $this->createAdmin();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $sender->id, 'total_balance' => 1000]);
        UserBalance::create(['user_id' => $receiver->id, 'total_balance' => 0]);

        $this->actingAs($sender, 'api')
            ->postJson('/api/send-tip', [
                'receiver_id' => $receiver->id,
                'tip_amount' => 100,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Tip sent successfully.',
            ]);

        $this->assertEqualsWithDelta(900.0, (float) UserBalance::where('user_id', $sender->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(50.0, (float) UserBalance::where('user_id', $receiver->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(50.0, (float) UserBalance::where('user_id', $receiver->id)->value('total_tip_received'), 0.0001);
        $this->assertEqualsWithDelta(50.0, (float) UserBalance::where('user_id', $admin->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(50.0, (float) UserBalance::where('user_id', $admin->id)->value('total_tip_received'), 0.0001);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $sender->id,
            'type' => 'tip',
            'reference' => 'Tip sent to user #'.$receiver->id,
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $receiver->id,
            'type' => 'tip',
            'reference' => 'Tip received from user #'.$sender->id,
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $admin->id,
            'type' => 'tip',
            'reference' => 'Admin share from tip sent by user #'.$sender->id,
        ]);

        $this->assertDatabaseHas('tips', [
            'send_user_id' => $sender->id,
            'received_user_id' => $receiver->id,
        ]);

        $tip = Tip::where('send_user_id', $sender->id)->first();
        $this->assertEqualsWithDelta(100.0, (float) $tip->tip_amount, 0.0001);
    }

    public function test_tipping_the_admin_credits_the_full_amount_to_the_admin(): void
    {
        Notification::fake();

        $admin = $this->createAdmin();
        $sender = User::factory()->create();

        UserBalance::create(['user_id' => $sender->id, 'total_balance' => 200]);

        $this->actingAs($sender, 'api')
            ->postJson('/api/send-tip', [
                'receiver_id' => $admin->id,
                'tip_amount' => 60,
            ])
            ->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertEqualsWithDelta(140.0, (float) UserBalance::where('user_id', $sender->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(60.0, (float) UserBalance::where('user_id', $admin->id)->value('total_balance'), 0.0001);
        $this->assertEqualsWithDelta(60.0, (float) UserBalance::where('user_id', $admin->id)->value('total_tip_received'), 0.0001);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $admin->id,
            'type' => 'tip',
            'reference' => 'Tip received from user #'.$sender->id,
        ]);
    }

    public function test_tip_fails_with_insufficient_balance(): void
    {
        Notification::fake();

        $this->createAdmin();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        UserBalance::create(['user_id' => $sender->id, 'total_balance' => 10]);
        UserBalance::create(['user_id' => $receiver->id, 'total_balance' => 0]);

        $this->actingAs($sender, 'api')
            ->postJson('/api/send-tip', [
                'receiver_id' => $receiver->id,
                'tip_amount' => 100,
            ])
            ->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Insufficient balance.',
            ]);

        $this->assertEqualsWithDelta(10.0, (float) UserBalance::where('user_id', $sender->id)->value('total_balance'), 0.0001);
        $this->assertDatabaseCount('coin_transactions', 0);
        $this->assertDatabaseCount('tips', 0);
    }
}
