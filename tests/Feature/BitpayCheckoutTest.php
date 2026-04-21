<?php

namespace Tests\Feature;

use App\Models\BitpayPayment;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use RuntimeException;
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
            'services.bitpay.base_url' => 'https://sandbox.bitpay.test',
            'services.bitpay.token' => 'bitpay-token',
            'services.bitpay.webhook_url' => 'https://hooks.bitpay.test/bitpay/webhook',
        ]);
    }

    public function test_authenticated_user_can_start_a_bitpay_checkout(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        Http::fake([
            'https://sandbox.bitpay.test/invoices' => Http::response([
                'data' => [
                    'id' => 'BITPAY-INVOICE-1001',
                    'url' => 'https://pay.bitpay.test/invoice?id=BITPAY-INVOICE-1001',
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
            ->assertJsonPath('data.url', 'https://pay.bitpay.test/invoice?id=BITPAY-INVOICE-1001');

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://sandbox.bitpay.test/invoices'
                && $request->hasHeader('X-Accept-Version', '2.0.0')
                && ! $request->hasHeader('Authorization')
                && $request['token'] === 'bitpay-token'
                && (float) $request['price'] === 25.0
                && $request['currency'] === 'USD'
                && is_string($request['orderId'])
                && $request['orderId'] !== ''
                && $request['notificationURL'] === 'https://hooks.bitpay.test/bitpay/webhook'
                && $request['redirectURL'] === 'https://frontend.test/payment-success?provider=bitpay'
                && $request['fullNotifications'] === true
                && $request['extendedNotifications'] === true;
        });

        $this->assertDatabaseHas('bitpay_payments', [
            'user_id' => $user->id,
            'bitpay_invoice_id' => 'BITPAY-INVOICE-1001',
            'amount' => '25.00',
            'coin_amount' => '25.00',
            'status' => 'pending',
        ]);
    }

    public function test_bitpay_checkout_requires_a_public_https_webhook_url(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        config([
            'app.url' => 'http://127.0.0.1:8000',
            'services.bitpay.webhook_url' => null,
        ]);

        Http::fake();

        $this->withoutExceptionHandling();

        try {
            $this->withHeaders($this->authHeadersFor($user))
                ->postJson('/api/checkout', [
                    'amount' => 10,
                    'payment_method' => 'bitpay',
                ]);

            $this->fail('Expected the BitPay checkout to reject a non-HTTPS webhook URL.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('BitPay webhook URL must use HTTPS.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_bitpay_webhook_accepts_the_invoice_id_payload_and_credits_points(): void
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
            'https://sandbox.bitpay.test/invoices/BITPAY-INVOICE-2002*' => Http::response([
                'data' => [
                    'id' => 'BITPAY-INVOICE-2002',
                    'status' => 'confirmed',
                    'buyer' => [
                        'email' => 'buyer@example.test',
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/bitpay/webhook', [
            'id' => 'BITPAY-INVOICE-2002',
            'status' => 'confirmed',
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request): bool {
            return str_starts_with($request->url(), 'https://sandbox.bitpay.test/invoices/BITPAY-INVOICE-2002')
                && $request->hasHeader('X-Accept-Version', '2.0.0')
                && str_contains($request->url(), 'token=bitpay-token');
        });

        $this->assertDatabaseHas('bitpay_payments', [
            'order_id' => 'order-2002',
            'bitpay_invoice_id' => 'BITPAY-INVOICE-2002',
            'payer' => 'buyer@example.test',
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
