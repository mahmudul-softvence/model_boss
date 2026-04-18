<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use RuntimeException;

class MoncashService
{
    public function createCheckout(float $amount, string $orderId): array
    {

        $htgAmmout = $this->normalizeAmount($amount) * 130;

        $response = $this->apiRequest()
            ->post($this->apiUrl('/v1/CreatePayment'), [
                'amount' => $htgAmmout,
                'orderId' => $orderId,
            ])
            ->throw();

        $paymentToken = data_get($response->json(), 'payment_token.token');

        if (! is_string($paymentToken) || $paymentToken === '') {
            throw new RuntimeException('MonCash did not return a payment token.');
        }

        return [
            'token' => $paymentToken,
            'url' => $this->gatewayUrl($paymentToken),
        ];
    }

    public function retrievePaymentFromCallback(?string $encryptedTransactionId, ?string $orderId = null): array
    {
        if ($encryptedTransactionId) {
            return $this->retrievePaymentByTransactionId(
                $this->decryptTransactionId($encryptedTransactionId)
            );
        }

        if ($orderId) {
            return $this->retrievePaymentByOrderId($orderId);
        }

        throw new RuntimeException('Missing MonCash payment identifier.');
    }

    public function retrievePaymentByTransactionId(string $transactionId): array
    {
        $response = $this->apiRequest()
            ->post($this->apiUrl('/v1/RetrieveTransactionPayment'), [
                'transactionId' => $transactionId,
            ])
            ->throw();

        return $this->extractPaymentDetails($response);
    }

    public function retrievePaymentByOrderId(string $orderId): array
    {
        $response = $this->apiRequest()
            ->post($this->apiUrl('/v1/RetrieveOrderPayment'), [
                'orderId' => $orderId,
            ])
            ->throw();

        return $this->extractPaymentDetails($response);
    }

    public function decryptTransactionId(string $encryptedTransactionId): string
    {
        $ciphertext = base64_decode(str_replace(' ', '+', $encryptedTransactionId), true);

        if ($ciphertext === false) {
            throw new RuntimeException('Invalid MonCash transaction payload.');
        }

        foreach ($this->loadMoncashKeys() as $key) {
            $transactionId = $this->decryptCiphertext($key, $ciphertext);

            if ($transactionId !== null) {
                return $transactionId;
            }
        }

        throw new RuntimeException('Unable to decrypt the MonCash transaction identifier.');
    }

    protected function accessToken(): string
    {
        return Cache::remember($this->accessTokenCacheKey(), now()->addSeconds(45), function (): string {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(15)
                ->retry(2, 200)
                ->withBasicAuth(
                    $this->requiredConfig('services.moncash.client_id'),
                    $this->requiredConfig('services.moncash.client_secret'),
                )
                ->post($this->apiUrl('/oauth/token'), [
                    'scope' => 'read,write',
                    'grant_type' => 'client_credentials',
                ])
                ->throw();

            $accessToken = data_get($response->json(), 'access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('MonCash did not return an access token.');
            }

            return $accessToken;
        });
    }

    protected function apiRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(10)
            ->timeout(15)
            ->retry(2, 200)
            ->withToken($this->accessToken());
    }

    protected function extractPaymentDetails(Response $response): array
    {
        $payload = $response->json();
        $paymentDetails = data_get($payload, 'payment');

        if (! is_array($paymentDetails)) {
            if (is_array($payload) && array_key_exists('reference', $payload)) {
                return $payload;
            }

            throw new RuntimeException('MonCash did not return payment details.');
        }

        return $paymentDetails;
    }

    protected function gatewayUrl(string $paymentToken): string
    {
        return rtrim($this->requiredConfig('services.moncash.gateway_base'), '/')
            .'/Payment/Redirect?token='
            .urlencode($paymentToken);
    }

    protected function apiUrl(string $path): string
    {
        return rtrim($this->requiredConfig('services.moncash.base_url'), '/').$path;
    }

    protected function loadMoncashKeys(): array
    {
        $apiKey = trim($this->requiredConfig('services.moncash.api_key'));
        $decodedApiKey = base64_decode($apiKey, true);
        $candidateKeys = array_filter([
            $apiKey,
            $decodedApiKey !== false && $decodedApiKey !== '' ? $decodedApiKey : null,
            $decodedApiKey !== false && $decodedApiKey !== '' ? $this->toPem($decodedApiKey, 'PUBLIC KEY') : null,
            $decodedApiKey !== false && $decodedApiKey !== '' ? $this->toPem($decodedApiKey, 'RSA PUBLIC KEY') : null,
            $decodedApiKey !== false && $decodedApiKey !== '' ? $this->toPem($decodedApiKey, 'PRIVATE KEY') : null,
            $decodedApiKey !== false && $decodedApiKey !== '' ? $this->toPem($decodedApiKey, 'RSA PRIVATE KEY') : null,
        ], static fn (?string $candidateKey): bool => is_string($candidateKey) && trim($candidateKey) !== '');

        $loadedKeys = [];

        foreach ($candidateKeys as $candidateKey) {
            try {
                $loadedKeys[] = PublicKeyLoader::load($candidateKey);
            } catch (\Throwable) {
            }
        }

        if ($loadedKeys === []) {
            throw new RuntimeException('Unable to load the MonCash API key.');
        }

        return $loadedKeys;
    }

    protected function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing MonCash configuration for [{$key}].");
        }

        return trim($value);
    }

    protected function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }

    protected function decryptCiphertext(AsymmetricKey $key, string $ciphertext): ?string
    {
        $decryptedValue = $this->decryptWithPrivateKey($key, $ciphertext)
            ?? $this->decryptWithPublicKey($key, $ciphertext);

        if (! is_string($decryptedValue)) {
            return null;
        }

        $decryptedValue = trim(ltrim($decryptedValue, "\0"));

        return $decryptedValue !== '' ? $decryptedValue : null;
    }

    protected function decryptWithPrivateKey(AsymmetricKey $key, string $ciphertext): ?string
    {
        try {
            return $key->asPrivateKey()
                ->withPadding(RSA::ENCRYPTION_NONE)
                ->decrypt($ciphertext);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function decryptWithPublicKey(AsymmetricKey $key, string $ciphertext): ?string
    {
        try {
            return $key->asPublicKey()
                ->withPadding(RSA::ENCRYPTION_NONE)
                ->encrypt($ciphertext);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function accessTokenCacheKey(): string
    {
        return 'moncash.access_token.'.md5(implode('|', [
            $this->requiredConfig('services.moncash.base_url'),
            $this->requiredConfig('services.moncash.client_id'),
        ]));
    }

    protected function toPem(string $key, string $type): string
    {
        return "-----BEGIN {$type}-----\n"
            .chunk_split(base64_encode($key), 64, "\n")
            ."-----END {$type}-----";
    }
}
