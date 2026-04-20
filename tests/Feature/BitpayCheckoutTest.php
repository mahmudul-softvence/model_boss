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

class BitpayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://backend.test',
            'app.frontend_url' => 'https://frontend.test',
            'services.bitpay.base_url' => 'https://bitpay.test',
            'services.bitpay.token' => 'bitpay-token',
        ]);
    }

    public function test_authenticated_user_can_start_a_bitpay_checkout(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        Http::fake([
            'https://bitpay.test/api/invoices' => Http::response([
                'data' => [
                    'id' => 'BITPAY-INVOICE-1001',
                    'url' => 'https://bitpay.test/invoice?id=BITPAY-INVOICE-1001',
                ],
            ], 201),
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
            $payload = $request->data();

            return $request->url() === 'https://bitpay.test/api/invoices'
                && (float) data_get($payload, 'price', 0) === 25.0
                && data_get($payload, 'currency') === 'USD'
                && str_ends_with((string) data_get($payload, 'notificationURL', ''), '/bitpay/webhook')
                && data_get($payload, 'redirectURL') === 'https://frontend.test/payment-success?provider=bitpay';
        });

        $this->assertDatabaseHas('bitpay_payments', [
            'user_id' => $user->id,
            'bitpay_invoice_id' => 'BITPAY-INVOICE-1001',
            'amount' => '25.00',
            'coin_amount' => '25.00',
            'status' => 'pending',
        ]);
    }

    public function test_bitpay_webhook_marks_payment_completed_and_credits_coins(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        BitpayPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-2002',
            'bitpay_invoice_id' => 'BITPAY-INVOICE-2002',
            'amount' => 15,
            'coin_amount' => 15,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://bitpay.test/api/invoices/BITPAY-INVOICE-2002' => Http::response([
                'data' => [
                    'id' => 'BITPAY-INVOICE-2002',
                    'status' => 'confirmed',
                    'buyer' => [
                        'email' => 'buyer@example.com',
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/bitpay/webhook', [
            'invoiceId' => 'BITPAY-INVOICE-2002',
            'status' => 'confirmed',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Payment processed',
            ]);

        $this->assertDatabaseHas('bitpay_payments', [
            'order_id' => 'order-2002',
            'bitpay_invoice_id' => 'BITPAY-INVOICE-2002',
            'payer' => 'buyer@example.com',
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
        $this->assertDatabaseHas('user_balances', [
            'user_id' => 1,
            'total_recharge' => '15.00',
        ]);
    }

    public function test_bitpay_webhook_marks_failed_payments_without_crediting_coins(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        BitpayPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-3003',
            'bitpay_invoice_id' => 'BITPAY-INVOICE-3003',
            'amount' => 18,
            'coin_amount' => 18,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://bitpay.test/api/invoices/BITPAY-INVOICE-3003' => Http::response([
                'data' => [
                    'id' => 'BITPAY-INVOICE-3003',
                    'status' => 'expired',
                ],
            ]),
        ]);

        $response = $this->postJson('/bitpay/webhook', [
            'invoiceId' => 'BITPAY-INVOICE-3003',
            'status' => 'expired',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Payment failed',
            ]);

        $this->assertDatabaseHas('bitpay_payments', [
            'order_id' => 'order-3003',
            'status' => 'failed',
        ]);
        $this->assertDatabaseMissing('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'recharge',
        ]);
        $this->assertNull(UserBalance::where('user_id', $user->id)->value('id'));
    }

    private function createAdmin(): User
    {
        return User::factory()->create();
    }

    /**
     * @return array<string, string>
     */
    private function authHeadersFor(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser($user),
        ];
    }
}
