<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WithdrawRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }

        config([
            'services.paypal.base_url' => 'https://api-m.paypal.test',
            'services.paypal.client_id' => 'client-id',
            'services.paypal.client_secret' => 'client-secret',
            'cache.default' => 'array',
        ]);

        Setting::updateOrCreate(
            ['key' => 'auto_accept_withdrawals'],
            ['value' => 'false'],
        );
    }

    public function test_paypal_withdraw_holds_balance_as_pending_when_auto_accept_disabled(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        $user = User::factory()->create([
            'paypal_email' => 'payout@example.com',
        ]);
        $user->userBalance()->create([
            'total_balance' => 100,
            'total_withdraw' => 0,
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/paypal/withdraw', [
                'coin_amount' => 20,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $user->id,
            'payment_method' => 'paypal',
            'coin_amount' => 20,
            'status' => WithdrawalStatus::PENDING->value,
        ]);

        $this->assertEquals(80.0, (float) $user->userBalance()->value('total_balance'));
        $this->assertEquals(0.0, (float) $user->userBalance()->value('total_withdraw'));
        $this->assertDatabaseMissing('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'withdraw',
        ]);
    }

    public function test_paypal_auto_accept_pays_out_outside_hold_transaction(): void
    {
        Setting::updateOrCreate(
            ['key' => 'auto_accept_withdrawals'],
            ['value' => 'true'],
        );

        $admin = User::factory()->create();
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        $user = User::factory()->create([
            'paypal_email' => 'payout@example.com',
        ]);
        $user->userBalance()->create([
            'total_balance' => 100,
            'total_withdraw' => 0,
        ]);

        Http::fake([
            'https://api-m.paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ]),
            'https://api-m.paypal.test/v1/payments/payouts' => Http::response([
                'batch_header' => [
                    'payout_batch_id' => 'BATCH-123',
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/paypal/withdraw', [
                'coin_amount' => 25,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', WithdrawalStatus::ACCEPTED->value);

        $this->assertEquals(75.0, (float) $user->userBalance()->value('total_balance'));
        $this->assertEquals(25.0, (float) $user->userBalance()->value('total_withdraw'));

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $user->id,
            'paypal_payout_id' => 'BATCH-123',
            'status' => WithdrawalStatus::ACCEPTED->value,
        ]);

        Http::assertSentCount(2);
    }

    public function test_paypal_auto_accept_refunds_hold_when_payout_fails(): void
    {
        Setting::updateOrCreate(
            ['key' => 'auto_accept_withdrawals'],
            ['value' => 'true'],
        );

        $admin = User::factory()->create();
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        $user = User::factory()->create([
            'paypal_email' => 'payout@example.com',
        ]);
        $user->userBalance()->create([
            'total_balance' => 50,
            'total_withdraw' => 0,
        ]);

        Http::fake([
            'https://api-m.paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ]),
            'https://api-m.paypal.test/v1/payments/payouts' => Http::response([
                'message' => 'payout failed',
            ], 500),
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/paypal/withdraw', [
                'coin_amount' => 30,
            ]);

        $response->assertStatus(400);

        $this->assertEquals(50.0, (float) $user->userBalance()->value('total_balance'));
        $this->assertEquals(0.0, (float) $user->userBalance()->value('total_withdraw'));

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $user->id,
            'status' => WithdrawalStatus::DECLINED->value,
        ]);
    }

    private function authHeadersFor(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser($user),
        ];
    }
}
