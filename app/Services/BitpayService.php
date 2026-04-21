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
                'notificationURL' => $this->webhookUrl(),
                'redirectURL' => $this->frontendRedirectUrl('bitpay'),
                'fullNotifications' => true,
                'extendedNotifications' => true,
            ])
        );
        $data = $this->extractInvoiceData($response);

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

        return $this->extractInvoiceData($response);
    }

    public function getInvoiceStatus(string $invoiceId): string
    {
        $invoice = $this->retrieveInvoice($invoiceId);

        return $invoice['status'] ?? 'new';
    }

    public function isPaymentCompleted(array $invoice): bool
    {
        $status = $invoice['status'] ?? '';

        return in_array($status, ['confirmed', 'complete', 'paid'], true);
    }

    public function isPaymentFailed(array $invoice): bool
    {
        $status = $invoice['status'] ?? '';

        return in_array($status, ['expired', 'declined', 'failed'], true);
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

    protected function webhookUrl(): string
    {
        $configuredUrl = config('services.bitpay.webhook_url');
        $webhookUrl = is_string($configuredUrl) && trim($configuredUrl) !== ''
            ? trim($configuredUrl)
            : route('bitpay.webhook');

        if (! str_starts_with(strtolower($webhookUrl), 'https://')) {
            throw new RuntimeException('BitPay webhook URL must use HTTPS. Set APP_URL or BITPAY_WEBHOOK_URL to a public HTTPS URL.');
        }

        return $webhookUrl;
    }

    protected function frontendRedirectUrl(string $provider): string
    {
        return rtrim($this->requiredConfig('app.frontend_url'), '/')
            .'/payment-success?provider='.$provider;
    }

    protected function ensureSuccessfulResponse(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = $this->extractErrorMessage($response);

        throw new RuntimeException('BitPay request failed: '.$message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractInvoiceData(Response $response): array
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('BitPay did not return invoice data.');
        }

        return $data;
    }

    protected function extractErrorMessage(Response $response): string
    {
        $errorMessage = data_get($response->json(), 'error.message');

        if (is_string($errorMessage) && trim($errorMessage) !== '') {
            return trim($errorMessage);
        }

        $body = trim($response->body());

        if ($body !== '') {
            return $body;
        }

        return 'Unexpected response from BitPay.';
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
