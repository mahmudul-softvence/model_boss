<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitpayService
{
    public function createCheckout(float $amount, string $orderId): array
    {
        $response = $this->ensureSuccessfulResponse($this->apiRequest()
            ->post($this->apiUrl('/invoices'), [
                'token' => $this->requiredConfig('services.bitpay.token'),
                'price' => $this->normalizeAmount($amount),
                'currency' => 'USD',
                'orderId' => $orderId,
                'notificationURL' => route('bitpay.webhook'),
                'redirectURL' => $this->frontendRedirectUrl('payment-success', 'bitpay'),
                'fullNotifications' => true,
                'extendedNotifications' => true,
            ])
        );

        $data = $this->extractData($response);
        $invoiceId = $data['id'] ?? null;
        $invoiceUrl = $data['url'] ?? null;

        if (! $invoiceId || ! $invoiceUrl) {
            throw new RuntimeException('BitPay invoice creation failed.');
        }

        return [
            'invoice_id' => $invoiceId,
            'url' => $invoiceUrl,
        ];
    }

    public function retrieveInvoice(string $invoiceId): array
    {
        $response = $this->ensureSuccessfulResponse($this->apiRequest()
            ->get($this->apiUrl("/invoices/{$invoiceId}"), [
                'token' => $this->requiredConfig('services.bitpay.token'),
            ])
        );

        return $this->extractData($response);
    }

    public function isPaymentCompleted(array $invoice): bool
    {
        return in_array($invoice['status'] ?? '', ['confirmed', 'complete', 'paid'], true);
    }

    public function isPaymentFailed(array $invoice): bool
    {
        return in_array($invoice['status'] ?? '', ['expired', 'declined', 'failed'], true);
    }

    protected function apiRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'X-Accept-Version' => '2.0.0',
            ])
            ->connectTimeout(10)
            ->timeout(15)
            ->retry(2, 200);
    }

    protected function apiUrl(string $path): string
    {
        return rtrim($this->requiredConfig('services.bitpay.base_url'), '/').$path;
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

        throw new RuntimeException('BitPay request failed: '.$this->extractErrorMessage($response));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractData(Response $response): array
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('BitPay did not return invoice data.');
        }

        return $data;
    }

    protected function extractErrorMessage(Response $response): string
    {
        $message = data_get($response->json(), 'error.message');

        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        $body = trim($response->body());

        return $body !== '' ? $body : 'Unexpected response from BitPay.';
    }

    protected function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }

    protected function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing BitPay configuration for [{$key}].");
        }

        return trim($value);
    }
}
