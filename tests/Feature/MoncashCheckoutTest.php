<?php

namespace Tests\Feature;

use App\Models\MoncashPayment;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class MoncashCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'https://frontend.test',
            'cache.default' => 'array',
            'services.moncash.base_url' => 'https://sandbox.moncash.test',
            'services.moncash.gateway_base' => 'https://gateway.moncash.test',
            'services.moncash.client_id' => 'client-id',
            'services.moncash.client_secret' => 'client-secret',
        ]);

        Cache::flush();
    }

    public function test_authenticated_user_can_start_a_moncash_checkout(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        Http::fake([
            'https://sandbox.moncash.test/oauth/token' => Http::response([
                'access_token' => 'moncash-token',
            ]),
            'https://sandbox.moncash.test/v1/CreatePayment' => Http::response([
                'payment_token' => [
                    'token' => 'checkout-token',
                ],
            ], 202),
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/checkout', [
                'amount' => 25,
                'payment_method' => 'moncash',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'moncash')
            ->assertJsonPath('data.url', 'https://gateway.moncash.test/Payment/Redirect?token=checkout-token');

        Http::assertSentCount(2);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://sandbox.moncash.test/v1/CreatePayment'
                && $request->hasHeader('Authorization', 'Bearer moncash-token')
                && (float) $request['amount'] === 25.0
                && is_string($request['orderId'])
                && $request['orderId'] !== '';
        });

        $this->assertDatabaseHas('moncash_payments', [
            'user_id' => $user->id,
            'amount' => '25.00',
            'coin_amount' => '25.00',
            'status' => 'pending',
        ]);
    }

    public function test_moncash_callback_marks_payment_completed_and_credits_points(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        MoncashPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-1001',
            'amount' => 15,
            'coin_amount' => 15,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://sandbox.moncash.test/oauth/token' => Http::response([
                'access_token' => 'moncash-token',
            ]),
            'https://sandbox.moncash.test/v1/RetrieveOrderPayment' => Http::response([
                'payment' => [
                    'reference' => 'order-1001',
                    'transaction_id' => 'tx-1001',
                    'cost' => 15,
                    'message' => 'successful',
                    'payer' => '50937007294',
                ],
            ]),
        ]);

        $response = $this->get('/moncash/callback?orderId=order-1001');

        $response->assertRedirect('https://frontend.test/payment-success?provider=moncash');

        $this->assertDatabaseHas('moncash_payments', [
            'order_id' => 'order-1001',
            'transaction_id' => 'tx-1001',
            'payer' => '50937007294',
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
            'reference' => 'tx-1001',
        ]);
        $this->assertDatabaseHas('user_balances', [
            'user_id' => 1,
            'total_recharge' => '15.00',
        ]);
    }

    public function test_moncash_callback_is_idempotent_for_completed_payments(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        MoncashPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-2002',
            'amount' => 30,
            'coin_amount' => 30,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://sandbox.moncash.test/oauth/token' => Http::response([
                'access_token' => 'moncash-token',
            ]),
            'https://sandbox.moncash.test/v1/RetrieveOrderPayment' => Http::response([
                'payment' => [
                    'reference' => 'order-2002',
                    'transaction_id' => 'tx-2002',
                    'cost' => 30,
                    'message' => 'successful',
                    'payer' => '50937007294',
                ],
            ]),
        ]);

        $this->get('/moncash/callback?orderId=order-2002')
            ->assertRedirect('https://frontend.test/payment-success?provider=moncash');
        $this->get('/moncash/callback?orderId=order-2002')
            ->assertRedirect('https://frontend.test/payment-success?provider=moncash');

        $this->assertSame('30.00', UserBalance::where('user_id', $user->id)->value('total_balance'));
        $this->assertSame(1, $user->coinTransactions()->count());
    }

    public function test_moncash_callback_marks_failed_payments_and_redirects_to_cancel(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        MoncashPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-3003',
            'amount' => 18,
            'coin_amount' => 18,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://sandbox.moncash.test/oauth/token' => Http::response([
                'access_token' => 'moncash-token',
            ]),
            'https://sandbox.moncash.test/v1/RetrieveOrderPayment' => Http::response([
                'payment' => [
                    'reference' => 'order-3003',
                    'cost' => 18,
                    'message' => 'failed',
                    'payer' => '50937007294',
                ],
            ]),
        ]);

        $response = $this->get('/moncash/callback?orderId=order-3003');

        $response->assertRedirect('https://frontend.test/payment-cancel?provider=moncash');

        $this->assertDatabaseHas('moncash_payments', [
            'order_id' => 'order-3003',
            'status' => 'failed',
            'payer' => '50937007294',
        ]);
        $this->assertDatabaseMissing('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'recharge',
        ]);
        $this->assertNull(UserBalance::where('user_id', $user->id)->value('id'));
    }

    public function test_moncash_callback_accepts_encrypted_transaction_ids_and_legacy_response_keys(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        MoncashPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-4004',
            'amount' => 22,
            'coin_amount' => 22,
            'status' => 'pending',
        ]);

        $callbackPayload = $this->encryptedTransactionIdPayload('tx-4004');

        config([
            'services.moncash.api_key' => $callbackPayload['api_key'],
        ]);

        Http::fake([
            'https://sandbox.moncash.test/oauth/token' => Http::response([
                'access_token' => 'moncash-token',
            ]),
            'https://sandbox.moncash.test/v1/RetrieveTransactionPayment' => Http::response([
                'payment' => [
                    'reference' => 'order-4004',
                    'transNumber' => 'tx-4004',
                    'cost' => 22,
                    'payment_msg' => 'Successful',
                    'payer' => '50937007294',
                ],
            ]),
        ]);

        $response = $this->get('/moncash/callback?transactionId='.urlencode($callbackPayload['transaction_id']));

        $response->assertRedirect('https://frontend.test/payment-success?provider=moncash');

        $this->assertDatabaseHas('moncash_payments', [
            'order_id' => 'order-4004',
            'transaction_id' => 'tx-4004',
            'payer' => '50937007294',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'recharge',
            'amount' => '22.00',
            'reference' => 'tx-4004',
        ]);
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

    /**
     * @return array{api_key: string, transaction_id: string}
     */
    private function encryptedTransactionIdPayload(string $transactionId): array
    {
        $privateKey = RSA::createKey(1024)->withPadding(RSA::ENCRYPTION_NONE);
        $publicKeyPem = $privateKey->getPublicKey()->withPadding(RSA::ENCRYPTION_NONE)->toString('PKCS8');

        $apiKey = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $publicKeyPem);

        return [
            'api_key' => $apiKey,
            'transaction_id' => base64_encode($privateKey->decrypt($transactionId)),
        ];
    }
}
