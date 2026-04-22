<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CoinbaseService
{
    public function createCheckout(float $amount, string $orderId): array
    {
        $response = $this->ensureSuccessfulResponse($this->apiRequest()
            ->post($this->apiUrl('/charges'), [
                'name' => "Order {$orderId}",
                'description' => "Point purchase for order {$orderId}",
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => $this->normalizeAmount($amount),
                    'currency' => 'USD',
                ],
                'redirect_url' => $this->frontendRedirectUrl('payment-success', 'coinbase'),
                'cancel_url' => $this->frontendRedirectUrl('payment-cancel', 'coinbase'),
                'metadata' => [
                    'order_id' => $orderId,
                ],
            ])
        );
        $data = $this->extractChargeData($response);

        $chargeId = $data['id'] ?? null;
        $hostedUrl = $data['hosted_url'] ?? null;

        if (! $chargeId || ! $hostedUrl) {
            throw new RuntimeException('Coinbase charge creation failed.');
        }

        return [
            'charge_id' => $chargeId,
            'url' => $hostedUrl,
        ];
    }

    public function retrieveCharge(string $chargeId): array
    {
        $response = $this->ensureSuccessfulResponse($this->apiRequest()
            ->get($this->apiUrl("/charges/{$chargeId}"))
        );

        return $this->extractChargeData($response);
    }

    public function isPaymentCompleted(array $charge): bool
    {
        return in_array($this->chargeStatus($charge), ['COMPLETED', 'RESOLVED'], true);
    }

    public function isPaymentFailed(array $charge): bool
    {
        return in_array($this->chargeStatus($charge), ['EXPIRED', 'CANCELED', 'UNRESOLVED'], true);
    }

    protected function apiRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'X-CC-Api-Key' => $this->requiredConfig('services.coinbase.api_key'),
            ])
            ->connectTimeout(10)
            ->timeout(15)
            ->retry(2, 200);
    }

    protected function apiUrl(string $path): string
    {
        return rtrim($this->requiredConfig('services.coinbase.base_url'), '/').$path;
    }

    protected function frontendRedirectUrl(string $path, string $provider): string
    {
        return rtrim($this->requiredConfig('app.frontend_url'), '/')
            ."/{$path}?provider={$provider}";
    }

    protected function ensureSuccessfulResponse(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = $this->extractErrorMessage($response);

        throw new RuntimeException('Coinbase request failed: '.$message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractChargeData(Response $response): array
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Coinbase did not return charge data.');
        }

        return $data;
    }

    protected function extractErrorMessage(Response $response): string
    {
        $errorMessage = data_get($response->json(), 'error.message')
            ?? data_get($response->json(), 'error');

        if (is_string($errorMessage) && trim($errorMessage) !== '') {
            return trim($errorMessage);
        }

        $body = trim($response->body());

        if ($body !== '') {
            return $body;
        }

        return 'Unexpected response from Coinbase.';
    }

    protected function chargeStatus(array $charge): string
    {
        $timeline = $charge['timeline'] ?? [];

        if (! is_array($timeline) || $timeline === []) {
            return '';
        }

        $latestStep = end($timeline);

        if (! is_array($latestStep)) {
            return '';
        }

        $status = $latestStep['status'] ?? '';

        return is_string($status) ? strtoupper($status) : '';
    }

    protected function normalizeAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    protected function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing Coinbase configuration for [{$key}].");
        }

        return trim($value);
    }
}
