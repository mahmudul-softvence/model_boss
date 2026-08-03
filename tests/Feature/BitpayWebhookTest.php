<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\BitpayPayment;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BitpayWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bitpay.base_url' => 'https://bitpay.test',
            'services.bitpay.token' => 'bitpay-token',
        ]);
    }

    public function test_confirmed_invoice_credits_user_once(): void
    {
        User::factory()->create(); // admin balance target (id 1)
        $user = User::factory()->create();
        $user->userBalance()->create(['total_balance' => 0]);

        BitpayPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-bitpay-1',
            'bitpay_invoice_id' => 'INV-1',
            'amount' => 25,
            'coin_amount' => 25,
            'status' => PaymentStatus::PENDING->value,
        ]);

        Http::fake([
            'https://bitpay.test/invoices/INV-1*' => Http::response([
                'data' => [
                    'id' => 'INV-1',
                    'orderId' => 'order-bitpay-1',
                    'price' => 25,
                    'status' => 'confirmed',
                    'buyer' => ['email' => 'buyer@example.com'],
                ],
            ]),
        ]);

        $this->postJson('/bitpay/webhook', [
            'invoiceId' => 'INV-1',
            'status' => 'confirmed',
        ])->assertOk();

        $this->postJson('/bitpay/webhook', [
            'invoiceId' => 'INV-1',
            'status' => 'confirmed',
        ])->assertOk();

        $this->assertDatabaseHas('bitpay_payments', [
            'bitpay_invoice_id' => 'INV-1',
            'status' => PaymentStatus::COMPLETED->value,
        ]);

        $this->assertSame(1, $user->coinTransactions()->count());
        $this->assertEquals(25.0, (float) UserBalance::where('user_id', $user->id)->value('total_balance'));
    }

    public function test_paid_status_does_not_credit_until_confirmed(): void
    {
        User::factory()->create();
        $user = User::factory()->create();

        BitpayPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-bitpay-2',
            'bitpay_invoice_id' => 'INV-2',
            'amount' => 10,
            'coin_amount' => 10,
            'status' => PaymentStatus::PENDING->value,
        ]);

        Http::fake([
            'https://bitpay.test/invoices/INV-2*' => Http::response([
                'data' => [
                    'id' => 'INV-2',
                    'orderId' => 'order-bitpay-2',
                    'price' => 10,
                    'status' => 'paid',
                ],
            ]),
        ]);

        $this->postJson('/bitpay/webhook', [
            'invoiceId' => 'INV-2',
            'status' => 'paid',
        ])->assertOk();

        $this->assertDatabaseHas('bitpay_payments', [
            'bitpay_invoice_id' => 'INV-2',
            'status' => PaymentStatus::PENDING->value,
        ]);
        $this->assertDatabaseMissing('coin_transactions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        User::factory()->create();
        $user = User::factory()->create();

        BitpayPayment::create([
            'user_id' => $user->id,
            'order_id' => 'order-bitpay-3',
            'bitpay_invoice_id' => 'INV-3',
            'amount' => 10,
            'coin_amount' => 10,
            'status' => PaymentStatus::PENDING->value,
        ]);

        Http::fake([
            'https://bitpay.test/invoices/INV-3*' => Http::response([
                'data' => [
                    'id' => 'INV-3',
                    'orderId' => 'order-bitpay-3',
                    'price' => 999,
                    'status' => 'confirmed',
                ],
            ]),
        ]);

        $this->postJson('/bitpay/webhook', [
            'invoiceId' => 'INV-3',
            'status' => 'confirmed',
        ])->assertStatus(400);

        $this->assertDatabaseHas('bitpay_payments', [
            'bitpay_invoice_id' => 'INV-3',
            'status' => PaymentStatus::PENDING->value,
        ]);
    }
}
