<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaypalService
{
    /**
     * @return array{paypal_order_id: string, url: string}
     */
    public function createCheckout(User $user, float $amount, string $orderId): array
    {
        $response = $this->apiRequest()
            ->withHeaders([
                'PayPal-Request-Id' => $orderId,
                'Prefer' => 'return=representation',
            ])
            ->post($this->apiUrl('/v2/checkout/orders'), $this->checkoutPayload($user, $amount, $orderId))
            ->throw();

        $paypalOrderId = $response->json('id');
        $approvalUrl = $this->approvalUrl($response->json('links', []));

        if (! is_string($paypalOrderId) || trim($paypalOrderId) === '') {
            throw new RuntimeException('PayPal did not return an order ID.');
        }

        if ($approvalUrl === null) {
            throw new RuntimeException('PayPal did not return an approval URL.');
        }

        return [
            'paypal_order_id' => trim($paypalOrderId),
            'url' => $approvalUrl,
        ];
    }

    public function sendPayout(string $receiverEmail, float $amount, string $reference): string
    {
        $response = $this->apiRequest()
            ->post($this->apiUrl('/v1/payments/payouts'), [
                'sender_batch_header' => [
                    'sender_batch_id' => $reference,
                    'email_subject' => 'You have a payout from '.config('app.name'),
                ],
                'items' => [[
                    'recipient_type' => 'EMAIL',
                    'receiver' => $receiverEmail,
                    'amount' => [
                        'value' => $this->normalizeAmount($amount),
                        'currency' => 'USD',
                    ],
                    'note' => 'Withdrawal '.$reference,
                    'sender_item_id' => $reference,
                ]],
            ])
            ->throw();

        $batchId = $response->json('batch_header.payout_batch_id');

        if (! is_string($batchId) || trim($batchId) === '') {
            throw new RuntimeException('PayPal did not return a payout batch ID.');
        }

        return trim($batchId);
    }

    public function captureApprovedOrder(string $paypalOrderId): array
    {
        return $this->apiRequest()
            ->withHeaders([
                'PayPal-Request-Id' => 'capture-'.$paypalOrderId,
                'Prefer' => 'return=representation',
            ])
            ->withBody('{}', 'application/json')
            ->post($this->apiUrl("/v2/checkout/orders/{$paypalOrderId}/capture"))
            ->throw()
            ->json();
    }

    protected function apiRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->connectTimeout(10)
            ->timeout(20)
            ->retry(2, 200)
            ->withToken($this->accessToken());
    }

    protected function accessToken(): string
    {
        return Cache::remember($this->accessTokenCacheKey(), now()->addMinutes(10), function (): string {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(15)
                ->retry(2, 200)
                ->withBasicAuth(
                    $this->requiredConfig('services.paypal.client_id'),
                    $this->requiredConfig('services.paypal.client_secret'),
                )
                ->post($this->apiUrl('/v1/oauth2/token'), [
                    'grant_type' => 'client_credentials',
                ])
                ->throw();

            $accessToken = $response->json('access_token');

            if (! is_string($accessToken) || trim($accessToken) === '') {
                throw new RuntimeException('PayPal did not return an access token.');
            }

            return trim($accessToken);
        });
    }

    protected function checkoutPayload(User $user, float $amount, string $orderId): array
    {
        $paypalSource = [
            'experience_context' => [
                'brand_name' => config('app.name'),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => route('paypal.return'),
                'cancel_url' => route('paypal.cancel'),
            ],
        ];

        if (is_string($user->email) && trim($user->email) !== '') {
            $paypalSource['email_address'] = trim($user->email);
        }

        return [
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => $paypalSource,
            ],
            'purchase_units' => [[
                'reference_id' => $orderId,
                'custom_id' => $orderId,
                'invoice_id' => $orderId,
                'description' => 'Coin purchase',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $this->normalizeAmount($amount),
                ],
            ]],
        ];
    }

    protected function approvalUrl(mixed $links): ?string
    {
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $rel = strtolower((string) ($link['rel'] ?? ''));
            $href = $link['href'] ?? null;

            if (in_array($rel, ['payer-action', 'approve'], true) && is_string($href) && trim($href) !== '') {
                return trim($href);
            }
        }

        return null;
    }

    protected function normalizeAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    protected function apiUrl(string $path): string
    {
        return rtrim($this->requiredConfig('services.paypal.base_url'), '/').$path;
    }

    protected function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing PayPal configuration for [{$key}].");
        }

        return trim($value);
    }

    protected function accessTokenCacheKey(): string
    {
        return 'paypal.access_token.'.md5(implode('|', [
            $this->requiredConfig('services.paypal.base_url'),
            $this->requiredConfig('services.paypal.client_id'),
        ]));
    }
}
