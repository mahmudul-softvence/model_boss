<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitpayService
{
    public function createCheckout(float $amount, string $orderId): array
    {
        $token = $this->requiredConfig('services.bitpay.token');

        $response = $this->apiRequest()
            ->post($this->apiUrl('/invoices'), [
                'price' => $amount,
                'currency' => 'USD',
                'orderId' => $orderId,
                'notificationURL' => $this->webhookUrl(),
                'redirectURL' => $this->frontendRedirectUrl('bitpay'),
                'fullNotifications' => true,
                'extendedNotifications' => true,
            ])
            ->throw();

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('BitPay did not return invoice data.');
        }

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
        $response = $this->apiRequest()
            ->get($this->apiUrl("/invoices/{$invoiceId}"))
            ->throw();

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('BitPay did not return invoice data.');
        }

        return $data;
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
            ->connectTimeout(10)
            ->timeout(15)
            ->retry(2, 200)
            ->withToken($this->requiredConfig('services.bitpay.token'));
    }

    protected function apiUrl(string $path): string
    {
        return rtrim($this->requiredConfig('services.bitpay.base_url'), '/').'/api'.$path;
    }

    protected function webhookUrl(): string
    {
        return route('bitpay.webhook');
    }

    protected function frontendRedirectUrl(string $provider): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/payment-success?provider='.$provider;
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
