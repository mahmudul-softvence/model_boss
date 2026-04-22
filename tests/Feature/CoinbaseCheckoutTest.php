<?php

namespace Tests\Feature;

use App\Models\CoinbasePayment;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CoinbaseCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'https://frontend.test',
            'services.coinbase.base_url' => 'https://commerce.coinbase.test',
            'services.coinbase.api_key' => 'coinbase-api-key',
        ]);
    }

    public function test_authenticated_user_can_start_a_coinbase_checkout(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        Http::fake([
            'https://commerce.coinbase.test/charges' => Http::response([
                'data' => [
                    'id' => 'COINBASE-CHARGE-1001',
                    'hosted_url' => 'https://pay.coinbase.test/charges/COINBASE-CHARGE-1001',
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/checkout', [
                'amount' => 25,
                'payment_method' => 'coinbase',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'coinbase')
            ->assertJsonPath('data.url', 'https://pay.coinbase.test/charges/COINBASE-CHARGE-1001');

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://commerce.coinbase.test/charges'
                && $request->hasHeader('X-CC-Api-Key', 'coinbase-api-key')
                && $request['pricing_type'] === 'fixed_price'
                && $request['local_price']['amount'] === '25.00'
                && $request['local_price']['currency'] === 'USD'
                && $request['redirect_url'] === 'https://frontend.test/payment-success?provider=coinbase'
                && $request['cancel_url'] === 'https://frontend.test/payment-cancel?provider=coinbase'
                && is_string($request['metadata']['order_id'])
                && $request['metadata']['order_id'] !== '';
        });

        $this->assertDatabaseHas('coinbase_payments', [
            'user_id' => $user->id,
            'coinbase_charge_id' => 'COINBASE-CHARGE-1001',
            'amount' => '25.00',
            'coin_amount' => '25.00',
            'status' => 'pending',
        ]);
    }

    public function test_coinbase_webhook_credits_points_after_completed_charge(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        CoinbasePayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-coinbase-2002',
            'coinbase_charge_id' => 'COINBASE-CHARGE-2002',
            'amount' => 15,
            'coin_amount' => 15,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://commerce.coinbase.test/charges/COINBASE-CHARGE-2002' => Http::response([
                'data' => [
                    'id' => 'COINBASE-CHARGE-2002',
                    'timeline' => [
                        ['status' => 'NEW'],
                        ['status' => 'COMPLETED'],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/coinbase/webhook', [
            'event' => [
                'type' => 'charge:confirmed',
                'data' => [
                    'id' => 'COINBASE-CHARGE-2002',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://commerce.coinbase.test/charges/COINBASE-CHARGE-2002'
                && $request->hasHeader('X-CC-Api-Key', 'coinbase-api-key');
        });

        $this->assertDatabaseHas('coinbase_payments', [
            'order_id' => 'order-coinbase-2002',
            'coinbase_charge_id' => 'COINBASE-CHARGE-2002',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $user->id,
            'total_balance' => '15.00',
            'total_recharge' => '15.00',
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'recharge',
            'amount' => '15.00',
            'reference' => 'COINBASE-CHARGE-2002',
        ]);
        $this->assertSame('15.00', (string) UserBalance::where('user_id', 1)->value('total_recharge'));
    }

    private function createAdmin(): User
    {
        return User::factory()->create();
    }

    private function authHeadersFor(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser($user),
        ];
    }
}
