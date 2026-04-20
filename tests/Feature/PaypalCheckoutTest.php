<?php

namespace Tests\Feature;

use App\Models\PaypalPayment;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PaypalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.name' => 'Model Boss',
            'app.url' => 'https://backend.test',
            'app.frontend_url' => 'https://frontend.test',
            'cache.default' => 'array',
            'services.paypal.base_url' => 'https://api-m.sandbox.paypal.test',
            'services.paypal.client_id' => 'client-id',
            'services.paypal.client_secret' => 'client-secret',
        ]);

        Cache::flush();
    }

    public function test_authenticated_user_can_start_a_paypal_checkout(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        Http::fake([
            'https://api-m.sandbox.paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ]),
            'https://api-m.sandbox.paypal.test/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-1001',
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [
                    [
                        'rel' => 'self',
                        'href' => 'https://api-m.sandbox.paypal.test/v2/checkout/orders/PAYPAL-ORDER-1001',
                    ],
                    [
                        'rel' => 'payer-action',
                        'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1001',
                    ],
                ],
            ], 201),
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/checkout', [
                'amount' => 25,
                'payment_method' => 'paypal',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'paypal')
            ->assertJsonPath('data.url', 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1001');

        Http::assertSentCount(2);
        Http::assertSent(function (HttpRequest $request) use ($user): bool {
            $payload = $request->data();
            $returnUrl = (string) data_get($payload, 'payment_source.paypal.experience_context.return_url', '');
            $cancelUrl = (string) data_get($payload, 'payment_source.paypal.experience_context.cancel_url', '');

            return $request->url() === 'https://api-m.sandbox.paypal.test/v2/checkout/orders'
                && data_get($payload, 'intent') === 'CAPTURE'
                && data_get($payload, 'purchase_units.0.amount.value') === '25.00'
                && data_get($payload, 'purchase_units.0.description') === 'Coin purchase'
                && data_get($payload, 'payment_source.paypal.email_address') === $user->email
                && str_ends_with($returnUrl, '/paypal/return')
                && str_ends_with($cancelUrl, '/paypal/cancel');
        });

        $this->assertDatabaseHas('paypal_payments', [
            'user_id' => $user->id,
            'paypal_order_id' => 'PAYPAL-ORDER-1001',
            'amount' => '25.00',
            'coin_amount' => '25.00',
            'status' => 'pending',
        ]);
    }

    public function test_paypal_return_captures_payment_and_credits_coins(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        PaypalPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-2002',
            'paypal_order_id' => 'PAYPAL-ORDER-2002',
            'amount' => 15,
            'coin_amount' => 15,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ]),
            'https://api-m.sandbox.paypal.test/v2/checkout/orders/PAYPAL-ORDER-2002/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-2002',
                'status' => 'COMPLETED',
                'payer' => [
                    'email_address' => 'buyer@example.com',
                ],
                'purchase_units' => [[
                    'custom_id' => 'order-2002',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => '15.00',
                    ],
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAPTURE-2002',
                            'status' => 'COMPLETED',
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => '15.00',
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->get('/paypal/return?token=PAYPAL-ORDER-2002');

        $response->assertRedirect('https://frontend.test/payment-success?provider=paypal');

        $this->assertDatabaseHas('paypal_payments', [
            'order_id' => 'order-2002',
            'paypal_order_id' => 'PAYPAL-ORDER-2002',
            'capture_id' => 'CAPTURE-2002',
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
            'reference' => 'CAPTURE-2002',
        ]);
        $this->assertDatabaseHas('user_balances', [
            'user_id' => 1,
            'total_recharge' => '15.00',
        ]);
    }

    public function test_paypal_return_is_idempotent_for_completed_payments(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        PaypalPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-3003',
            'paypal_order_id' => 'PAYPAL-ORDER-3003',
            'amount' => 30,
            'coin_amount' => 30,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ]),
            'https://api-m.sandbox.paypal.test/v2/checkout/orders/PAYPAL-ORDER-3003/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-3003',
                'status' => 'COMPLETED',
                'payer' => [
                    'email_address' => 'buyer@example.com',
                ],
                'purchase_units' => [[
                    'custom_id' => 'order-3003',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => '30.00',
                    ],
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAPTURE-3003',
                            'status' => 'COMPLETED',
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => '30.00',
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->get('/paypal/return?token=PAYPAL-ORDER-3003')
            ->assertRedirect('https://frontend.test/payment-success?provider=paypal');
        $this->get('/paypal/return?token=PAYPAL-ORDER-3003')
            ->assertRedirect('https://frontend.test/payment-success?provider=paypal');

        Http::assertSentCount(2);
        $this->assertSame('30.00', UserBalance::where('user_id', $user->id)->value('total_balance'));
        $this->assertSame(1, $user->coinTransactions()->count());
    }

    public function test_paypal_cancel_marks_payment_failed_and_redirects_to_cancel_page(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        PaypalPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-4004',
            'paypal_order_id' => 'PAYPAL-ORDER-4004',
            'amount' => 18,
            'coin_amount' => 18,
            'status' => 'pending',
        ]);

        $response = $this->get('/paypal/cancel?token=PAYPAL-ORDER-4004&errorcode=buyer_cancelled');

        $response->assertRedirect('https://frontend.test/payment-cancel?provider=paypal&reason=buyer_cancelled');

        $this->assertDatabaseHas('paypal_payments', [
            'order_id' => 'order-4004',
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

    private function authHeadersFor(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser($user),
        ];
    }
}
