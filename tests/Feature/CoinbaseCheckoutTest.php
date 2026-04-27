<?php

namespace Tests\Feature;

use App\Models\BitpayPayment;
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
            'services.bitpay.base_url' => 'https://bitpay.test',
            'services.bitpay.token' => 'bitpay-api-token',
        ]);
    }

    public function test_authenticated_user_can_start_a_bitpay_checkout(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        Http::fake([
            'https://bitpay.test/invoices' => Http::response([
                'data' => [
                    'id' => 'BITPAY-INVOICE-1001',
                    'url' => 'https://bitpay.test/invoice?id=BITPAY-INVOICE-1001',
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/checkout', [
                'amount' => 25,
                'payment_method' => 'bitpay',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'bitpay')
            ->assertJsonPath('data.url', 'https://bitpay.test/invoice?id=BITPAY-INVOICE-1001');

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://bitpay.test/invoices'
                && $request['token'] === 'bitpay-api-token'
                && $request['price'] == 25.00
                && $request['currency'] === 'USD'
                && is_string($request['orderId'])
                && $request['orderId'] !== '';
        });

        $this->assertDatabaseHas('bitpay_payments', [
            'user_id' => $user->id,
            'bitpay_invoice_id' => 'BITPAY-INVOICE-1001',
            'amount' => '25.00',
            'coin_amount' => '25.00',
            'status' => 'pending',
        ]);
    }

    public function test_bitpay_webhook_credits_points_after_completed_invoice(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        BitpayPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-bitpay-2002',
            'bitpay_invoice_id' => 'BITPAY-INVOICE-2002',
            'amount' => 15,
            'coin_amount' => 15,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://bitpay.test/invoices/BITPAY-INVOICE-2002' => Http::response([
                'data' => [
                    'id' => 'BITPAY-INVOICE-2002',
                    'status' => 'complete',
                ],
            ]),
        ]);

        $response = $this->postJson('/bitpay/webhook', [
            'invoiceId' => 'BITPAY-INVOICE-2002',
            'status' => 'complete',
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        $this->assertDatabaseHas('bitpay_payments', [
            'order_id' => 'order-bitpay-2002',
            'bitpay_invoice_id' => 'BITPAY-INVOICE-2002',
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
            'reference' => 'BITPAY-INVOICE-2002',
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
