<?php

namespace Tests\Feature;

use App\Models\NetcashPayment;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class NetcashCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'https://frontend.test',
            'services.netcash.mode' => 'sandbox',
            'services.netcash.gateway_url' => 'https://paynow.netcash.test/site/paynow.aspx',
            'services.netcash.trace_url' => 'https://trace.netcash.test/PayNow/TransactionStatus/Check',
            'services.netcash.service_key' => 'service-key-123',
            'services.netcash.vendor_key' => 'vendor-key-456',
            'services.netcash.description' => 'Coin purchase',
        ]);
    }

    public function test_authenticated_user_can_start_a_netcash_checkout(): void
    {
        $this->createAdmin();
        $user = User::factory()->create([
            'email' => 'player@example.com',
            'phone_number' => '+1 (555) 123-4567',
        ]);

        $response = $this->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/checkout', [
                'amount' => 40,
                'payment_method' => 'netcash',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'netcash')
            ->assertJsonPath('data.url', 'https://paynow.netcash.test/site/paynow.aspx')
            ->assertJsonPath('data.method', 'POST')
            ->assertJsonPath('data.target', '_top')
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.fields.M1', 'service-key-123')
            ->assertJsonPath('data.fields.M2', 'vendor-key-456')
            ->assertJsonPath('data.fields.p3', 'SANDBOX Coin purchase')
            ->assertJsonPath('data.fields.p4', '40.00')
            ->assertJsonPath('data.fields.Budget', 'Y')
            ->assertJsonPath('data.fields.m6', 'sandbox')
            ->assertJsonPath('data.fields.m9', 'player@example.com')
            ->assertJsonPath('data.fields.m11', '1555123456');

        $reference = $response->json('data.reference');

        $this->assertIsString($reference);
        $this->assertSame($reference, $response->json('data.fields.p2'));
        $this->assertSame(25, strlen($reference));

        $this->assertDatabaseHas('netcash_payments', [
            'user_id' => $user->id,
            'reference' => $reference,
            'amount' => '40.00',
            'coin_amount' => '40.00',
            'status' => 'pending',
        ]);
    }

    public function test_netcash_notify_marks_payment_completed_and_credits_points(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        NetcashPayment::create([
            'user_id' => $user->id,
            'reference' => 'NC240101010101ABCDE123456',
            'amount' => 18,
            'coin_amount' => 18,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://trace.netcash.test/PayNow/TransactionStatus/Check*' => Http::response([
                'RequestTrace' => 'trace-1001',
                'Reference' => 'NC240101010101ABCDE123456',
                'Amount' => '18.00',
                'TransactionAccepted' => true,
                'Method' => '1',
            ]),
        ]);

        $response = $this->post('/netcash/notify', [
            'RequestTrace' => 'trace-1001',
            'Reference' => 'NC240101010101ABCDE123456',
        ]);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertDatabaseHas('netcash_payments', [
            'reference' => 'NC240101010101ABCDE123456',
            'request_trace' => 'trace-1001',
            'payment_method' => '1',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $user->id,
            'total_balance' => '18.00',
            'total_recharge' => '18.00',
        ]);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'recharge',
            'amount' => '18.00',
            'reference' => 'trace-1001',
        ]);
    }

    public function test_netcash_notify_is_idempotent_for_completed_payments(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        NetcashPayment::create([
            'user_id' => $user->id,
            'reference' => 'NC240101010101ABCDE654321',
            'amount' => 22,
            'coin_amount' => 22,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://trace.netcash.test/PayNow/TransactionStatus/Check*' => Http::response([
                'RequestTrace' => 'trace-2002',
                'Reference' => 'NC240101010101ABCDE654321',
                'Amount' => '22.00',
                'TransactionAccepted' => true,
                'Method' => '1',
            ]),
        ]);

        $this->post('/netcash/notify', [
            'RequestTrace' => 'trace-2002',
            'Reference' => 'NC240101010101ABCDE654321',
        ])->assertOk();

        $this->post('/netcash/notify', [
            'RequestTrace' => 'trace-2002',
            'Reference' => 'NC240101010101ABCDE654321',
        ])->assertOk();

        $this->assertSame('22.00', UserBalance::where('user_id', $user->id)->value('total_balance'));
        $this->assertSame(1, $user->coinTransactions()->count());
    }

    public function test_netcash_decline_redirect_marks_payment_failed(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        NetcashPayment::create([
            'user_id' => $user->id,
            'reference' => 'NC240101010101ABCDE777777',
            'amount' => 12,
            'coin_amount' => 12,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://trace.netcash.test/PayNow/TransactionStatus/Check*' => Http::response([
                'RequestTrace' => 'trace-3003',
                'Reference' => 'NC240101010101ABCDE777777',
                'Amount' => '12.00',
                'TransactionAccepted' => false,
                'Reason' => 'Invalid card number',
            ]),
        ]);

        $response = $this->post('/netcash/decline', [
            'RequestTrace' => 'trace-3003',
            'Reference' => 'NC240101010101ABCDE777777',
        ]);

        $response->assertRedirect('https://frontend.test/payment-cancel?provider=netcash');

        $this->assertDatabaseHas('netcash_payments', [
            'reference' => 'NC240101010101ABCDE777777',
            'request_trace' => 'trace-3003',
            'reason' => 'Invalid card number',
            'status' => 'failed',
        ]);
        $this->assertDatabaseMissing('coin_transactions', [
            'user_id' => $user->id,
            'type' => 'recharge',
        ]);
    }

    public function test_netcash_redirect_keeps_pending_payments_pending(): void
    {
        $this->createAdmin();
        $user = User::factory()->create();

        NetcashPayment::create([
            'user_id' => $user->id,
            'reference' => 'NC240101010101ABCDE888888',
            'amount' => 9,
            'coin_amount' => 9,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://trace.netcash.test/PayNow/TransactionStatus/Check*' => Http::response([
                'RequestTrace' => 'trace-4004',
                'Reference' => 'NC240101010101ABCDE888888',
                'Amount' => '9.00',
                'TransactionAccepted' => false,
                'Reason' => 'Pending payment',
                'Method' => '2',
            ]),
        ]);

        $response = $this->post('/netcash/redirect', [
            'RequestTrace' => 'trace-4004',
            'Reference' => 'NC240101010101ABCDE888888',
        ]);

        $response->assertRedirect('https://frontend.test/payment-cancel?provider=netcash&status=pending');

        $this->assertDatabaseHas('netcash_payments', [
            'reference' => 'NC240101010101ABCDE888888',
            'request_trace' => 'trace-4004',
            'payment_method' => '2',
            'reason' => 'Pending payment',
            'status' => 'pending',
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
}
